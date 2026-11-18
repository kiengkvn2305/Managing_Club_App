<?php

namespace App\Enums;

enum ClubRole: string
{
    case Owner = 'owner';
    case CoOwner = 'co_owner';
    case GeneralManager = 'general_manager';
    case Treasurer = 'treasurer';
    case HumanResources = 'hr_manager';
    case Equipment = 'equipment_manager';
    case Sporting = 'sporting_manager';
    case Member = 'member';

    public function managesRoster(): bool
    {
        return in_array($this, [self::Owner, self::CoOwner, self::GeneralManager, self::HumanResources, self::Sporting], true);
    }

    public function managesClub(): bool
    {
        return in_array($this, [self::Owner, self::CoOwner, self::GeneralManager], true);
    }
}
