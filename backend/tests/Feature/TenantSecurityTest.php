<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_search_notes_and_sort_by_given_name_in_both_directions(): void
    {
        [, $club] = $this->club();
        $first = $this->player($club, ['full_name' => 'Nguyen Van An', 'notes' => 'thuan chan trai']);
        $second = $this->player($club, ['full_name' => 'Le Van Binh']);
        $third = $this->player($club, ['full_name' => 'Tran Van Chi']);
        $url = "/api/v1/clubs/$club/members";
        $this->getJson("$url?q=chan%20trai")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first);
        $this->getJson("$url?sort=full_name&direction=asc")->assertOk()->assertJsonPath('data.0.id', $first)->assertJsonPath('data.1.id', $second)->assertJsonPath('data.2.id', $third);
        $this->getJson("$url?sort=full_name&direction=desc")->assertOk()->assertJsonPath('data.0.id', $third)->assertJsonPath('data.2.id', $first);
    }

    private function club(): array
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'web');

        return [$owner, $this->postJson('/api/v1/clubs', ['name' => 'UIT Football', 'formats' => ['7', '5', '11']])->assertCreated()->json('data.id')];
    }

    private function actor(int $club, string $role): User
    {
        $user = User::factory()->create();
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $user->id, 'role' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function team(int $club): int
    {
        return $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'First Team', 'format' => '7'])->assertCreated()->json('data.id');
    }

    private function player(int $club, array $data = []): int
    {
        return $this->postJson("/api/v1/clubs/$club/members", $data + ['full_name' => 'Nguyễn Văn An', 'status' => 'active'])->assertCreated()->json('data.id');
    }

    public function test_roster_sorting_uses_the_selected_teams_position_across_pages(): void
    {
        [, $club] = $this->club();
        $team = $this->team($club);
        $this->getJson("/api/v1/clubs/$club/teams")->assertOk()->assertJsonPath('data.0.members_count', 0);
        $first = $this->player($club, ['full_name' => 'An', 'date_of_birth' => '2000-01-01', 'position' => 'ST']);
        $second = $this->player($club, ['full_name' => 'Binh', 'date_of_birth' => '1990-01-01', 'position' => 'GK']);
        $this->patchJson("/api/v1/clubs/$club/teams/$team/players", ['player_ids' => [$first, $second]])->assertNoContent();
        $this->getJson("/api/v1/clubs/$club/teams")->assertOk()->assertJsonPath('data.0.members_count', 2);
        $this->patchJson("/api/v1/clubs/$club/teams/$team/players/$first/position", ['position' => 'GK'])->assertNoContent();
        $this->patchJson("/api/v1/clubs/$club/teams/$team/players/$second/position", ['position' => 'ST'])->assertNoContent();
        $this->getJson("/api/v1/clubs/$club/members?team_id=$team&sort=position&per_page=1&page=1")->assertOk()->assertJsonPath('data.0.id', $first);
        $this->getJson("/api/v1/clubs/$club/members?team_id=$team&sort=position&per_page=1&page=2")->assertOk()->assertJsonPath('data.0.id', $second);
        $this->getJson("/api/v1/clubs/$club/members?sort=date_of_birth")->assertOk()->assertJsonPath('data.0.id', $second);
    }

    public function test_creation_dashboard_and_audit_use_persisted_data(): void
    {
        [$owner,$club] = $this->club();
        $this->assertDatabaseHas('club_members', ['club_id' => $club, 'user_id' => $owner->id, 'role' => 'owner']);
        $this->getJson('/api/v1/clubs')->assertJsonPath('data.0.membership_role', 'owner');
        $this->team($club);
        $this->player($club);
        $this->player($club, ['status' => 'inactive']);
        $this->getJson("/api/v1/clubs/$club/dashboard")->assertOk()->assertJsonFragment(['members_count' => 2, 'active_members_count' => 1, 'teams_count' => 1, 'managers_count' => 1]);
        $this->assertDatabaseHas('audit_logs', ['club_id' => $club, 'action' => 'club.created']);
    }

    public function test_outsiders_and_system_admins_have_no_tenant_bypass(): void
    {
        [,$club] = $this->club();
        foreach ([false, true] as $admin) {
            $this->actingAs(User::factory()->create(['is_system_admin' => $admin]));
            $this->getJson('/api/v1/clubs')->assertJsonCount(0, 'data');
            foreach (['dashboard', 'members', 'teams'] as $path) {
                $this->getJson("/api/v1/clubs/$club/$path")->assertNotFound();
            }
            $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Intruder'])->assertNotFound();
        }
    }

    public function test_idor_and_cross_club_team_validation(): void
    {
        [$owner,$club] = $this->club();
        $team = $this->team($club);
        $player = $this->player($club, ['team_id' => $team]);
        [,$other] = $this->club();
        $otherTeam = $this->team($other);
        $otherPlayer = $this->player($other);
        $this->actingAs($owner);
        $this->patchJson("/api/v1/clubs/$club/members/$otherPlayer", ['full_name' => 'Stolen'])->assertNotFound();
        $this->deleteJson("/api/v1/clubs/$club/members/$otherPlayer")->assertNotFound();
        $this->patchJson("/api/v1/clubs/$club/members/$player", ['team_id' => $otherTeam])->assertUnprocessable()->assertJsonValidationErrors('team_id');
        $this->deleteJson("/api/v1/clubs/$club/members/$player")->assertNoContent();
        $this->assertDatabaseHas('club_members', ['club_id' => $club, 'user_id' => $owner->id, 'role' => 'owner']);
    }

    public function test_database_foreign_key_rejects_cross_tenant_team(): void
    {
        [,$club] = $this->club();
        $player = $this->player($club);
        [,$other] = $this->club();
        $team = $this->team($other);
        $this->expectException(QueryException::class);
        DB::table('player_profiles')->where('id', $player)->update(['team_id' => $team]);
    }

    public function test_jersey_uniqueness_includes_injured_but_allows_inactive(): void
    {
        [,$club] = $this->club();
        $team = $this->team($club);
        $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'First Team', 'format' => '7'])->assertUnprocessable();
        $this->player($club, ['team_id' => $team, 'jersey_number' => 9]);
        $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Dup', 'team_id' => $team, 'jersey_number' => 9, 'status' => 'injured'])->assertUnprocessable();
        $this->player($club, ['team_id' => $team, 'jersey_number' => 9, 'status' => 'inactive']);
    }

    public function test_five_a_side_positions_accept_multiple_players(): void
    {
        [, $club] = $this->club();
        $team = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Futsal', 'format' => '5'])->assertCreated()->json('data.id');
        foreach (['GK', 'FIXO', 'ALA', 'PIVOT', 'PIVOT'] as $index => $position) {
            $this->postJson("/api/v1/clubs/$club/members", ['full_name' => "Player $index", 'team_id' => $team, 'position' => $position])->assertCreated()->assertJsonPath('data.position', $position);
        }
        $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Invalid', 'team_id' => $team, 'position' => 'INVALID'])->assertUnprocessable()->assertJsonValidationErrors('position');
    }

    public function test_team_rename_respects_club_boundaries_permissions_and_unique_names(): void
    {
        [$owner, $club] = $this->club();
        $team = $this->team($club);
        $second = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Second Team', 'format' => '5'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'First Team'])->assertOk();
        $this->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.format', '7');
        $this->patchJson("/api/v1/clubs/$club/teams/$second", ['name' => 'Renamed'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($this->actor($club, 'member'))->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'Forbidden'])->assertForbidden();
        $this->actingAs($owner);
        [, $other] = $this->club();
        $foreignTeam = $this->team($other);
        $this->actingAs($owner)->patchJson("/api/v1/clubs/$club/teams/$foreignTeam", ['name' => 'Wrong club'])->assertNotFound();
    }

    public function test_only_owner_can_manage_membership_titles(): void
    {
        [$owner, $club] = $this->club();
        $member = $this->actor($club, 'member');
        $membership = DB::table('club_members')->where('club_id', $club)->where('user_id', $member->id)->value('id');
        $this->getJson("/api/v1/clubs/$club/club-memberships")->assertOk()->assertJsonCount(2, 'data');
        $this->patchJson("/api/v1/clubs/$club/club-memberships/$membership", ['title' => 'HLV'])->assertOk();
        $this->assertDatabaseHas('club_members', ['id' => $membership, 'title' => 'HLV', 'role' => 'member']);
        $this->actingAs($member)->getJson("/api/v1/clubs/$club/club-memberships")->assertForbidden();
        $this->patchJson("/api/v1/clubs/$club/club-memberships/$membership", ['title' => 'Tự phong'])->assertForbidden();
        $this->actingAs($owner);
        [, $other] = $this->club();
        $otherMembership = DB::table('club_members')->where('club_id', $other)->value('id');
        $this->actingAs($owner)->patchJson("/api/v1/clubs/$club/club-memberships/$otherMembership", ['title' => 'Wrong'])->assertNotFound();
    }

    public function test_team_deletion_keeps_players_and_removes_team_lineups(): void
    {
        [, $club] = $this->club();
        $team = $this->team($club);
        $player = $this->player($club, ['team_id' => $team, 'jersey_number' => 9]);
        $this->postJson("/api/v1/clubs/$club/lineups", ['team_id' => $team, 'opponent' => 'Guest', 'match_date' => '2026-12-01', 'players' => []])->assertCreated();
        $this->deleteJson("/api/v1/clubs/$club/teams/$team")->assertNoContent();
        $this->assertDatabaseMissing('teams', ['id' => $team]);
        $this->assertDatabaseMissing('match_lineups', ['team_id' => $team]);
        $this->assertDatabaseHas('player_profiles', ['id' => $player, 'team_id' => null, 'current_jersey' => null, 'jersey_number' => 9]);
    }

    public function test_club_formats_and_multiple_team_managers(): void
    {
        [$owner, $club] = $this->club();
        $this->getJson('/api/v1/clubs')->assertJsonPath('data.0.formats.0', '7');
        $this->patchJson("/api/v1/clubs/$club", ['formats' => ['5', '7', '11']])->assertOk()->assertJsonPath('data.formats', ['5', '7', '11']);
        $this->patchJson("/api/v1/clubs/$club", ['formats' => []])->assertUnprocessable();
        $team = $this->team($club);
        $captain = $this->player($club, ['team_id' => $team]);
        $this->patchJson("/api/v1/clubs/$club/teams/$team/captain", ['captain_id' => $captain])->assertOk();
        $second = $this->actor($club, 'member');
        $ids = DB::table('club_members')->where('club_id', $club)->whereIn('user_id', [$owner->id, $second->id])->pluck('id')->all();
        $this->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'New name', 'format' => '5', 'membership_ids' => $ids])->assertOk();
        $this->getJson("/api/v1/clubs/$club/teams")->assertJsonPath('data.0.format', '5')->assertJsonCount(2, 'data.0.managers')->assertJsonPath('data.0.captain_name', 'Nguyễn Văn An');
        $this->actingAs($second)->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'Denied', 'membership_ids' => []])->assertForbidden();
        $this->actingAs($owner)->deleteJson("/api/v1/clubs/$club/teams/$team")->assertNoContent();
        $this->assertDatabaseMissing('team_managers', ['team_id' => $team]);
    }

    public function test_team_format_change_preserves_existing_lineup_constraints(): void
    {
        [, $club] = $this->club();
        $team = $this->team($club);
        $entries = [];
        for ($i = 0; $i < 6; $i++) {
            $id = $this->player($club, ['full_name' => "Player $i", 'team_id' => $team]);
            $entries[] = ['player_id' => $id, 'role' => 'starter', 'x' => 50, 'y' => 50];
        }
        $this->postJson("/api/v1/clubs/$club/lineups", ['team_id' => $team, 'opponent' => 'Visitors', 'match_date' => '2026-12-01', 'players' => $entries])->assertCreated();
        $this->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'New name', 'format' => '5', 'membership_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->assertDatabaseHas('teams', ['id' => $team, 'name' => 'First Team', 'format' => '7']);
    }

    public function test_member_can_join_multiple_teams_and_be_used_in_lineup(): void
    {
        [, $club] = $this->club();
        $first = $this->team($club);
        $second = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Second', 'format' => '5'])->assertCreated()->json('data.id');
        $player = $this->player($club, ['team_id' => $first, 'jersey_number' => 10]);
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players", ['player_ids' => [$player]])->assertNoContent();
        $this->getJson("/api/v1/clubs/$club/members/$player")->assertJsonCount(2, 'data.teams');
        $this->getJson("/api/v1/clubs/$club/members?team_id=$second")->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/clubs/$club/teams/$second/captain", ['captain_id' => $player])->assertOk();
        $entry = ['player_id' => $player, 'role' => 'starter', 'x' => 50, 'y' => 50];
        $this->postJson("/api/v1/clubs/$club/lineups", ['team_id' => $second, 'opponent' => 'Guest', 'match_date' => '2026-12-01', 'players' => [$entry]])->assertCreated();
        $this->patchJson("/api/v1/clubs/$club/members/$player", ['notes' => 'Test'])->assertOk()->assertJsonCount(2, 'data.teams');
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players", ['player_ids' => []])->assertNoContent();
        $this->getJson("/api/v1/clubs/$club/members/$player")->assertJsonCount(1, 'data.teams');
        $this->assertNull(DB::table('teams')->where('id', $second)->value('captain_id'));
        $this->deleteJson("/api/v1/clubs/$club/teams/$first")->assertNoContent();
        $this->assertDatabaseHas('player_profiles', ['id' => $player, 'team_id' => null]);
    }

    public function test_team_jerseys_are_independent_and_survive_roster_changes(): void
    {
        [, $club] = $this->club();
        $first = $this->team($club);
        $second = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Second', 'format' => '5'])->assertCreated()->json('data.id');
        $player = $this->player($club, ['team_id' => $first, 'jersey_number' => 7]);
        $another = $this->player($club);
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players", ['player_ids' => [$player, $another]])->assertNoContent();
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players/$player/jersey", ['jersey_number' => 11])->assertNoContent();
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players/$another/jersey", ['jersey_number' => 11])->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players", ['player_ids' => [$player, $another]])->assertNoContent();
        $this->assertDatabaseHas('team_players', ['team_id' => $first, 'player_id' => $player, 'jersey_number' => 7]);
        $this->assertDatabaseHas('team_players', ['team_id' => $second, 'player_id' => $player, 'jersey_number' => 11]);
        $this->getJson("/api/v1/clubs/$club/members/$player")->assertJsonCount(2, 'data.teams')->assertJsonFragment(['jersey_number' => 11]);
        $this->patchJson("/api/v1/clubs/$club/teams/$first/players/$another/jersey", ['jersey_number' => 3])->assertNotFound();
        $this->actingAs($this->actor($club, 'member'))->patchJson("/api/v1/clubs/$club/teams/$second/players/$player/jersey", ['jersey_number' => 1])->assertForbidden();
    }

    public function test_team_positions_are_independent(): void
    {
        [, $club] = $this->club();
        $first = $this->team($club);
        $second = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Second', 'format' => '5'])->assertCreated()->json('data.id');
        $player = $this->player($club, ['team_id' => $first, 'position' => 'CB']);
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players", ['player_ids' => [$player]])->assertNoContent();
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players/$player/position", ['position' => 'PIVOT'])->assertNoContent();
        $this->assertDatabaseHas('team_players', ['team_id' => $first, 'player_id' => $player, 'position' => 'CB']);
        $this->assertDatabaseHas('team_players', ['team_id' => $second, 'player_id' => $player, 'position' => 'PIVOT']);
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players/$player/position", ['position' => 'INVALID'])->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club/teams/$second/players/$player/position", ['position' => 'CB'])->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club/teams/$first/players/$player/position", ['position' => 'PIVOT'])->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club/teams/$first/players/$player/position", ['position' => 'LW'])->assertUnprocessable();
        $third = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Eleven', 'format' => '11'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/clubs/$club/teams/$third/players", ['player_ids' => [$player]])->assertNoContent();
        $this->patchJson("/api/v1/clubs/$club/teams/$third/players/$player/position", ['position' => 'LW'])->assertNoContent();
        $this->patchJson("/api/v1/clubs/$club/teams/$third/players/$player/position", ['position' => 'PIVOT'])->assertUnprocessable();
    }

    public function test_only_owner_can_delete_club_and_deleted_club_is_hidden(): void
    {
        [$owner, $club] = $this->club();
        $team = $this->team($club);
        $player = $this->player($club, ['team_id' => $team]);
        $member = $this->actor($club, 'co_owner');
        $this->actingAs($member)->deleteJson("/api/v1/clubs/$club")->assertForbidden();
        $this->actingAs($owner)->deleteJson("/api/v1/clubs/$club")->assertNoContent();
        $this->assertSoftDeleted('clubs', ['id' => $club]);
        $this->assertDatabaseHas('teams', ['id' => $team, 'club_id' => $club]);
        $this->assertDatabaseHas('player_profiles', ['id' => $player, 'club_id' => $club]);
        $this->getJson('/api/v1/clubs')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/clubs/$club/dashboard")->assertNotFound();
        $this->actingAs($member)->getJson('/api/v1/clubs')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/clubs/$club/teams")->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['club_id' => $club, 'action' => 'club.deleted']);
    }

    public function test_roles_cannot_read_or_edit_unrelated_private_profiles(): void
    {
        [,$club] = $this->club();
        $this->player($club, ['email' => 'private@example.test']);
        foreach (['co_owner', 'general_manager', 'hr_manager', 'sporting_manager'] as $role) {
            $this->actingAs($this->actor($club, $role))->getJson("/api/v1/clubs/$club/members")->assertOk()->assertJsonPath('data.0.email', 'private@example.test');
        }
        foreach (['member', 'treasurer', 'equipment_manager'] as $role) {
            $this->actingAs($this->actor($club, $role));
            $this->getJson("/api/v1/clubs/$club/members")->assertForbidden();
            $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Denied'])->assertForbidden();
        }
    }

    public function test_invitation_email_expiry_replay_and_role_restrictions(): void
    {
        [$owner,$club] = $this->club();
        $invitee = User::factory()->create();
        $payload = ['email' => $invitee->email, 'role' => 'member'];
        $inv = $this->postJson("/api/v1/clubs/$club/invitations", $payload)->assertCreated()->json('data');
        $this->postJson("/api/v1/clubs/$club/invitations", $payload)->assertUnprocessable();
        $this->assertDatabaseMissing('club_invitations', ['token_hash' => $inv['token']]);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/invitations/accept', ['token' => $inv['token']])->assertUnprocessable();
        $this->actingAs($invitee)->postJson('/api/v1/invitations/accept', ['token' => $inv['token']])->assertOk();
        $this->postJson('/api/v1/invitations/accept', ['token' => $inv['token']])->assertUnprocessable();
        $this->assertDatabaseHas('club_members', ['club_id' => $club, 'user_id' => $invitee->id, 'role' => 'member']);
        $this->actingAs($owner);
        $this->postJson("/api/v1/clubs/$club/invitations", ['email' => 'new@example.test', 'role' => 'owner'])->assertUnprocessable();
        $next = User::factory()->create();
        $expired = $this->postJson("/api/v1/clubs/$club/invitations", ['email' => $next->email, 'role' => 'member'])->assertCreated()->json('data');
        $this->travel(8)->days();
        $this->actingAs($next)->postJson('/api/v1/invitations/accept', ['token' => $expired['token']])->assertUnprocessable();
        $this->travelBack();
        $this->actingAs($this->actor($club, 'hr_manager'))->postJson("/api/v1/clubs/$club/invitations", ['email' => 'manager@example.test', 'role' => 'general_manager'])->assertForbidden();
    }

    public function test_archive_blocks_roster_mutations_and_filters_are_validated(): void
    {
        [,$club] = $this->club();
        $this->player($club);
        $this->getJson("/api/v1/clubs/$club/members?per_page=500")->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club", ['status' => 'archived'])->assertOk();
        $this->postJson("/api/v1/clubs/$club/members",['full_name' => 'Blocked'])->assertForbidden();
    }
}
