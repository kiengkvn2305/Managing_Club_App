<?php

namespace App\Models;

use App\Enums\ClubRole;
use Illuminate\Database\Eloquent\Model;

class ClubMembership extends Model
{
    protected $table = 'club_members';

    protected $fillable = ['club_id', 'user_id', 'role', 'status'];

    protected function casts(): array
    {
        return ['role' => ClubRole::class];
    }
}
