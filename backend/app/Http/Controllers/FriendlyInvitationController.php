<?php
namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\FriendlyInvitation;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FriendlyInvitationController extends Controller
{
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('update', $club);
        $requests = DB::table('friendly_invitations')->join('clubs as source', 'source.id', '=', 'friendly_invitations.source_club_id')->join('clubs as target', 'target.id', '=', 'friendly_invitations.target_club_id')
            ->where(fn ($q) => $q->where('source_club_id', $club->id)->orWhere('target_club_id', $club->id))
            ->select('friendly_invitations.id', 'friendly_invitations.source_club_id', 'friendly_invitations.target_club_id', 'friendly_invitations.status', 'friendly_invitations.match_date', 'friendly_invitations.match_time', 'friendly_invitations.location', 'friendly_invitations.message', 'friendly_invitations.created_at', 'source.name as source_name', 'target.name as target_name')
            ->orderByDesc('friendly_invitations.created_at')->orderByDesc('friendly_invitations.id')->paginate(20);
        return response()->json(['data' => $requests->items(), 'meta' => ['current_page' => $requests->currentPage(), 'last_page' => $requests->lastPage(), 'total' => $requests->total()]]);
    }

    public function store(Request $request, AuditService $audit): mixed
    {
        $source = $request->attributes->get('club'); Gate::authorize('update', $source); abort_unless($source->status === 'active', 403);
        $data = $request->validate(['target_club_id' => ['required', 'integer'], 'match_date' => ['required', 'date_format:Y-m-d'], 'match_time' => ['nullable', 'date_format:H:i'], 'location' => ['nullable', 'string', 'max:160'], 'message' => ['nullable', 'string', 'max:1000']]);
        if ($data['match_date'] < CarbonImmutable::now('Asia/Ho_Chi_Minh')->toDateString()) throw ValidationException::withMessages(['match_date' => ['Ngày giao hữu phải từ hôm nay trở đi.']]);
        if ((int) $data['target_club_id'] === (int) $source->id) throw ValidationException::withMessages(['target_club_id' => ['Không thể mời chính CLB của mình.']]);
        $invite = DB::transaction(function () use ($source, $request, $data, $audit) {
            $ids = [$source->id, (int) $data['target_club_id']]; sort($ids, SORT_NUMERIC);
            $locked = Club::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = $locked->get($source->id); $target = $locked->get((int) $data['target_club_id']);
            abort_unless($current && $current->status === 'active' && $target && $target->status === 'active' && $target->is_discoverable, 404);
            Gate::authorize('update', $current);
            $pair = implode(':', $ids);
            if (FriendlyInvitation::where('pair_key', $pair)->where('pending_key', 'pending')->exists()) throw ValidationException::withMessages(['target_club_id' => ['Hai CLB đã có một lời mời đang chờ phản hồi.']]);
            $invite = FriendlyInvitation::create(['source_club_id' => $current->id, 'target_club_id' => $target->id, 'pair_key' => $pair, 'pending_key' => 'pending', 'status' => 'pending', 'match_date' => $data['match_date'], 'match_time' => $data['match_time'] ?? null, 'location' => $data['location'] ?? null, 'message' => $data['message'] ?? null, 'created_by' => $request->user()->id]);
            $audit->record($current, $request->user(), 'friendly.invited', $invite); return $invite;
        });
        return response()->json(['data' => ['id' => $invite->id, 'status' => $invite->status]], 201);
    }

    public function respond(Request $request, string $club, int $invitation, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('update', $tenant); abort_unless($tenant->status === 'active', 403);
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'rejected', 'cancelled'])]]);
        DB::transaction(function () use ($tenant, $request, $invitation, $data, $audit) {
            $club = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); Gate::authorize('update', $club); abort_unless($club->status === 'active', 403);
            $invite = FriendlyInvitation::whereKey($invitation)->where(fn ($q) => $q->where('source_club_id', $club->id)->orWhere('target_club_id', $club->id))->lockForUpdate()->firstOrFail();
            if ($invite->status !== 'pending') throw ValidationException::withMessages(['status' => ['Lời mời đã được xử lý. Hãy tải lại.']]);
            $allowed = $data['status'] === 'cancelled' ? (int) $invite->source_club_id === (int) $club->id : (int) $invite->target_club_id === (int) $club->id;
            abort_unless($allowed, 403);
            $invite->update(['status' => $data['status'], 'pending_key' => null, 'responded_by' => $request->user()->id]);
            $audit->record($club, $request->user(), 'friendly.'.$data['status'], $invite);
        });
        return response()->noContent();
    }
}
