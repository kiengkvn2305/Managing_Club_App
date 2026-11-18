<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerRequest;
use App\Http\Resources\PlayerResource;
use App\Services\AuditService;
use App\Services\ClubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlayerController extends Controller
{
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('viewRoster', $club);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'team_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'injured'])], 'per_page' => ['nullable', 'integer', 'between:1,100'], 'page' => ['nullable', 'integer', 'min:1'], 'sort' => ['nullable', Rule::in(['full_name', 'created_at', 'jersey_number'])]]);
        $query = $club->players()->with(['team', 'teams']);
        if (! empty($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('full_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
        }
        if (! empty($filters['team_id'])) {
            $query->whereHas('teams', fn ($q) => $q->where('teams.id', $filters['team_id']));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return PlayerResource::collection($query->orderBy($filters['sort'] ?? 'full_name', ($filters['sort'] ?? '') === 'created_at' ? 'desc' : 'asc')->orderBy('id')->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function show(Request $request, string $club, int $member): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('viewRoster', $tenant);

        return new PlayerResource($tenant->players()->with(['team', 'teams'])->findOrFail($member));
    }

    public function store(PlayerRequest $request, ClubService $service): mixed
    {
        return (new PlayerResource($service->savePlayer($request->attributes->get('club'), $request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function import(Request $request, ClubService $service): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('manageRoster', $club);
        $data = $request->validate(['players' => ['required', 'array', 'min:1', 'max:500'], 'players.*' => ['required', 'array:full_name,email,phone,date_of_birth,position,preferred_foot,status,notes']]);
        $rulesRequest = PlayerRequest::create('/', 'POST');
        $rulesRequest->attributes->set('club', $club);
        $validated = []; $errors = []; $emails = [];
        foreach ($data['players'] as $index => $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
            if (! empty($row['email'])) $row['email'] = mb_strtolower($row['email']);
            $validator = Validator::make($row, $rulesRequest->rules());
            if (! empty($row['email'])) {
                if (isset($emails[$row['email']])) $errors["players.$index.email"] = ['Email bị trùng trong file.'];
                $emails[$row['email']] = true;
            }
            if ($validator->fails()) foreach ($validator->errors()->messages() as $field => $messages) $errors["players.$index.$field"] = $messages;
            else $validated[] = $validator->validated();
        }
        if ($errors) throw ValidationException::withMessages($errors);
        DB::transaction(function () use ($club, $request, $validated, $service) {
            foreach ($validated as $row) $service->savePlayer($club, $request->user(), $row);
        });
        return response()->json(['data' => ['imported' => count($validated)]], 201);
    }

    public function update(PlayerRequest $request, string $club, int $member, ClubService $service): mixed
    {
        $tenant = $request->attributes->get('club');
        $tenant->players()->findOrFail($member);

        return new PlayerResource($service->savePlayer($tenant, $request->user(), $request->validated(), $member));
    }

    public function destroy(Request $request, string $club, int $member, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        DB::transaction(function () use ($tenant, $member, $request, $audit) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $player = $tenant->players()->whereKey($member)->lockForUpdate()->firstOrFail();
            $audit->record($tenant, $request->user(), 'player.deleted', $player);
            DB::table('teams')->where('club_id', $tenant->id)->where('captain_id', $player->id)->update(['captain_id' => null]);
            DB::table('team_players')->where('club_id', $tenant->id)->where('player_id', $player->id)->delete();
            DB::table('club_awards')->where('club_id', $tenant->id)->where('player_id', $player->id)->update(['player_id' => null]);
            $player->delete();
        });

        return response()->noContent();
    }
}
