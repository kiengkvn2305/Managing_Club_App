<?php

namespace App\Http\Controllers;

use App\Enums\ClubRole;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubMembershipController extends Controller
{
    private function owner(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        abort_unless($club->roleFor($request->user()) === ClubRole::Owner, 403);
        return $club;
    }

    public function index(Request $request): mixed
    {
        $club = $this->owner($request);
        $members = DB::table('club_members')->join('users', 'users.id', '=', 'club_members.user_id')
            ->where('club_members.club_id', $club->id)->where('club_members.status', 'active')
            ->select('club_members.id', 'club_members.role', 'club_members.title', 'users.name', 'users.email')
            ->orderBy('users.name')->get();
        return response()->json(['data' => $members]);
    }

    public function update(Request $request, string $club, int $membership, AuditService $audit): mixed
    {
        $tenant = $this->owner($request);
        abort_unless($tenant->status === 'active', 403);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:120']]);
        $member = $tenant->memberships()->findOrFail($membership);
        DB::transaction(function () use ($member, $data, $tenant, $request, $audit) {
            $member->forceFill(['title' => $data['title'] ?? null])->save();
            $audit->record($tenant, $request->user(), 'membership.title_updated', $member);
        });
        return response()->json(['data' => ['id' => $member->id, 'title' => $member->title]]);
    }
}
