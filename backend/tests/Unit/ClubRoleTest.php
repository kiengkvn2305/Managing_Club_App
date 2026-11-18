<?php

namespace Tests\Unit;

use App\Enums\ClubRole;
use PHPUnit\Framework\TestCase;

class ClubRoleTest extends TestCase
{
    public function test_finance_and_equipment_roles_do_not_grant_roster_access(): void
    {
        $this->assertFalse(ClubRole::Treasurer->managesRoster());
        $this->assertFalse(ClubRole::Equipment->managesRoster());
        $this->assertTrue(ClubRole::HumanResources->managesRoster());
        $this->assertFalse(ClubRole::HumanResources->managesClub());
    }
}
