<?php

namespace App\Policies;

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    public function viewFinance(User $user, Club $club): bool
    {
        $membership = $club->memberships()->where('user_id', $user->id)->where('status', 'active')->first();
        return $membership && ($membership->role === ClubRole::Owner || in_array($membership->title, ['Chủ nhiệm CLB', 'Phó chủ nhiệm CLB', 'Thủ quỹ', 'HLV'], true));
    }

    public function manageFinance(User $user, Club $club): bool
    {
        $membership = $club->memberships()->where('user_id', $user->id)->where('status', 'active')->first();
        return $club->status === 'active' && $membership && ($membership->role === ClubRole::Owner || $membership->title === 'Thủ quỹ');
    }
    public function delete(User $user, Club $club): bool
    {
        return $club->roleFor($user) === ClubRole::Owner;
    }
    public function update(User $user, Club $club): bool
    {
        return $club->roleFor($user)?->managesClub() ?? false;
    }

    public function viewRoster(User $user, Club $club): bool
    {
        return $club->roleFor($user)?->managesRoster() ?? false;
    }
    public function viewInventory(User $user, Club $club): bool
    {
        return in_array($club->roleFor($user), [ClubRole::Owner, ClubRole::CoOwner, ClubRole::GeneralManager, ClubRole::Equipment], true);
    }

    public function manageInventory(User $user, Club $club): bool
    {
        return $club->status === 'active' && $this->viewInventory($user, $club);
    }
    public function viewActivities(User $user, Club $club): bool
    {
        return $this->viewRoster($user, $club) || $club->memberships()->where('user_id', $user->id)->where('status', 'active')->where('title', 'Phó chủ nhiệm CLB')->exists();
    }

    public function manageActivities(User $user, Club $club): bool
    {
        return $club->status === 'active' && $this->viewActivities($user, $club);
    }

    public function manageRoster(User $user, Club $club): bool
    {
        return $club->status === 'active' && $this->viewRoster($user, $club);
    }

    public function invite(User $user, Club $club, string $role): bool
    {
        $actor = $club->roleFor($user);
        if ($club->status !== 'active' || $role === 'owner') {
            return false;
        }
        if ($role === 'co_owner') {
            return $actor === ClubRole::Owner;
        }
        if (in_array($actor, [ClubRole::Owner, ClubRole::CoOwner], true)) {
            return true;
        }

        return $role === 'member' && in_array($actor, [ClubRole::GeneralManager, ClubRole::HumanResources], true);
    }
}
