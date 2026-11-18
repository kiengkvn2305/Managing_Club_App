<?php
namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Club;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewInventory', $club);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'low_stock' => ['nullable', 'boolean'], 'archived' => ['nullable', 'boolean']]);
        $items = InventoryItem::where('club_id', $club->id)->where('archived', (bool) ($data['archived'] ?? false))
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%'))
            ->when($data['low_stock'] ?? false, fn ($q) => $q->whereColumn('quantity', '<=', 'low_stock_threshold'))
            ->orderBy('name')->orderBy('id')->paginate(50);
        return response()->json($items);
    }

    public function store(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageInventory', $club);
        $data = $this->validateItem($request);
        $item = DB::transaction(function () use ($club, $request, $data, $audit) {
            $lockedClub = Club::whereKey($club->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageInventory', $lockedClub);
            $item = InventoryItem::create($data + ['club_id' => $club->id, 'quantity' => 0]);
            $audit->record($club, $request->user(), 'inventory.created', $item);
            return $item;
        });
        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, string $club, int $item, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageInventory', $tenant); $data = $this->validateItem($request);
        DB::transaction(function () use ($tenant, $item, $data, $request, $audit) {
            $lockedClub = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageInventory', $lockedClub);
            $record = InventoryItem::where('club_id', $tenant->id)->where('archived', false)->whereKey($item)->lockForUpdate()->firstOrFail();
            if (($record->unit !== $data['unit'] || $record->kind !== $data['kind']) && DB::table('inventory_movements')->where('club_id', $tenant->id)->where('inventory_item_id', $record->id)->exists()) throw ValidationException::withMessages(['unit' => ['Không thể đổi đơn vị hoặc loại khi vật tư đã có lịch sử biến động.']]);
            $record->update($data); $audit->record($tenant, $request->user(), 'inventory.updated', $record);
        });
        return response()->noContent();
    }

    public function destroy(Request $request, string $club, int $item, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageInventory', $tenant);
        DB::transaction(function () use ($tenant, $item, $request, $audit) {
            $lockedClub = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageInventory', $lockedClub);
            $record = InventoryItem::where('club_id', $tenant->id)->where('archived', false)->whereKey($item)->lockForUpdate()->firstOrFail();
            if ($record->quantity !== 0) throw ValidationException::withMessages(['quantity' => ['Chỉ lưu trữ vật tư khi tồn kho bằng 0.']]);
            if (DB::table('inventory_assignments')->where('club_id', $tenant->id)->where('inventory_item_id', $record->id)->where('remaining', '>', 0)->exists()) throw ValidationException::withMessages(['quantity' => ['Còn trang bị đang cấp phát; hãy thu hồi trước khi lưu trữ.']]);
            $record->update(['archived' => true]); $audit->record($tenant, $request->user(), 'inventory.archived', $record);
        });
        return response()->noContent();
    }

    public function movements(Request $request, string $club, int $item): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('viewInventory', $tenant);
        InventoryItem::where('club_id', $tenant->id)->whereKey($item)->firstOrFail();
        return response()->json(DB::table('inventory_movements')->where('club_id', $tenant->id)->where('inventory_item_id', $item)->orderByDesc('id')->paginate(50));
    }

    public function restore(Request $request, string $club, int $item, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageInventory', $tenant);
        DB::transaction(function () use ($tenant, $item, $request, $audit) {
            $lockedClub = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageInventory', $lockedClub);
            $record = InventoryItem::where('club_id', $tenant->id)->where('archived', true)->whereKey($item)->lockForUpdate()->firstOrFail();
            $record->update(['archived' => false]); $audit->record($tenant, $request->user(), 'inventory.restored', $record);
        });
        return response()->noContent();
    }

    public function move(Request $request, string $club, int $item, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageInventory', $tenant);
        $data = $request->validate(['type' => ['required', 'in:in,out,adjust'], 'quantity' => ['required', 'integer', 'between:0,1000000'], 'reason' => ['required', 'string', 'max:500']]);
        if ($data['type'] !== 'adjust' && (int) $data['quantity'] === 0) throw ValidationException::withMessages(['quantity' => ['Số lượng phải lớn hơn 0.']]);
        $result = DB::transaction(function () use ($tenant, $item, $request, $data, $audit) {
            $lockedClub = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageInventory', $lockedClub);
            $record = InventoryItem::where('club_id', $tenant->id)->where('archived', false)->whereKey($item)->lockForUpdate()->firstOrFail();
            $change = match ($data['type']) { 'in' => (int) $data['quantity'], 'out' => -(int) $data['quantity'], default => (int) $data['quantity'] - $record->quantity };
            $next = $record->quantity + $change;
            if ($next < 0 || $next > 1000000) throw ValidationException::withMessages(['quantity' => ['Số lượng tồn phải từ 0 đến 1.000.000.']]);
            if ($change === 0) throw ValidationException::withMessages(['quantity' => ['Số lượng không thay đổi.']]);
            $record->update(['quantity' => $next]);
            $id = DB::table('inventory_movements')->insertGetId(['club_id' => $tenant->id, 'inventory_item_id' => $record->id, 'actor_id' => $request->user()->id, 'type' => $data['type'], 'change' => $change, 'balance_after' => $next, 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'inventory.moved', $record);
            return ['id' => $id, 'quantity' => $next];
        });
        return response()->json(['data' => $result], 201);
    }

    public function candidates(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageInventory', $club);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $columns = ['id', 'full_name'];
        if ($request->user()->can('viewRoster', $club)) $columns = array_merge($columns, ['date_of_birth', 'email']);
        $players = DB::table('player_profiles')->where('club_id', $club->id)
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where('full_name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%'))
            ->orderBy('full_name')->orderBy('id')->select($columns)->paginate(50);
        $teams = DB::table('team_players')->join('teams', function ($join) { $join->on('teams.id', '=', 'team_players.team_id')->on('teams.club_id', '=', 'team_players.club_id'); })
            ->where('team_players.club_id', $club->id)->whereIn('team_players.player_id', $players->getCollection()->pluck('id'))
            ->select('team_players.player_id', 'team_players.jersey_number', 'teams.name')->orderBy('teams.name')->get()->groupBy('player_id');
        $players->getCollection()->transform(function ($player) use ($teams) {
            $player->team_labels = $teams->get($player->id, collect())->map(fn ($team) => $team->name.($team->jersey_number === null ? '' : ' #'.$team->jersey_number))->values();
            return $player;
        });
        return response()->json($players);
    }

    public function assignments(Request $request, string $club, int $item): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('viewInventory', $tenant);
        InventoryItem::where('club_id', $tenant->id)->whereKey($item)->firstOrFail();
        return response()->json(DB::table('inventory_assignments')->where('club_id', $tenant->id)->where('inventory_item_id', $item)->orderByDesc('id')->paginate(50));
    }

    public function assign(Request $request, string $club, int $item, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageInventory', $tenant);
        $data = $request->validate(['player_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'between:1,1000000'], 'reason' => ['required', 'string', 'max:500']]);
        $result = DB::transaction(function () use ($tenant, $item, $request, $data, $audit) {
            $lockedClub = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageInventory', $lockedClub);
            $record = InventoryItem::where('club_id', $tenant->id)->where('archived', false)->whereKey($item)->lockForUpdate()->firstOrFail();
            if ($record->kind !== 'asset') throw ValidationException::withMessages(['quantity' => ['Chỉ cấp phát và thu hồi tài sản.']]);
            $player = DB::table('player_profiles')->where('club_id', $tenant->id)->where('id', $data['player_id'])->lockForUpdate()->first();
            if (! $player) throw ValidationException::withMessages(['player_id' => ['Thành viên không thuộc CLB này.']]);
            $quantity = (int) $data['quantity'];
            if ($quantity > $record->quantity) throw ValidationException::withMessages(['quantity' => ['Không đủ số lượng trong kho.']]);
            $next = $record->quantity - $quantity;
            $assignment = DB::table('inventory_assignments')->insertGetId(['club_id' => $tenant->id, 'inventory_item_id' => $record->id, 'player_id' => $player->id, 'player_name' => $player->full_name, 'quantity' => $quantity, 'remaining' => $quantity, 'actor_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $record->update(['quantity' => $next]);
            DB::table('inventory_movements')->insert(['club_id' => $tenant->id, 'inventory_item_id' => $record->id, 'inventory_assignment_id' => $assignment, 'actor_id' => $request->user()->id, 'type' => 'assign', 'change' => -$quantity, 'balance_after' => $next, 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'inventory.assigned', $record);
            return ['id' => $assignment, 'quantity' => $next];
        });
        return response()->json(['data' => $result], 201);
    }

    public function returnAssignment(Request $request, string $club, int $item, int $assignment, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageInventory', $tenant);
        $data = $request->validate(['quantity' => ['required', 'integer', 'between:1,1000000'], 'reason' => ['required', 'string', 'max:500']]);
        $result = DB::transaction(function () use ($tenant, $item, $assignment, $request, $data, $audit) {
            $lockedClub = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('manageInventory', $lockedClub);
            $record = InventoryItem::where('club_id', $tenant->id)->where('archived', false)->whereKey($item)->lockForUpdate()->firstOrFail();
            $loan = DB::table('inventory_assignments')->where('club_id', $tenant->id)->where('inventory_item_id', $record->id)->where('id', $assignment)->lockForUpdate()->first();
            if (! $loan) abort(404);
            $quantity = (int) $data['quantity'];
            if ($quantity > $loan->remaining) throw ValidationException::withMessages(['quantity' => ['Số thu hồi vượt quá số còn mượn.']]);
            $next = $record->quantity + $quantity;
            if ($next > 1000000) throw ValidationException::withMessages(['quantity' => ['Số tồn sau khi thu hồi vượt quá giới hạn.']]);
            DB::table('inventory_assignments')->where('id', $loan->id)->update(['remaining' => $loan->remaining - $quantity, 'updated_at' => now()]);
            $record->update(['quantity' => $next]);
            DB::table('inventory_movements')->insert(['club_id' => $tenant->id, 'inventory_item_id' => $record->id, 'inventory_assignment_id' => $loan->id, 'actor_id' => $request->user()->id, 'type' => 'return', 'change' => $quantity, 'balance_after' => $next, 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($tenant, $request->user(), 'inventory.returned', $record);
            return ['quantity' => $next, 'remaining' => $loan->remaining - $quantity];
        });
        return response()->json(['data' => $result], 201);
    }

    private function validateItem(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:100'], 'unit' => ['required', 'string', 'max:30'], 'kind' => ['required', 'in:consumable,asset'], 'low_stock_threshold' => ['required', 'integer', 'between:0,1000000'], 'notes' => ['nullable', 'string', 'max:2000']]);
    }
}
