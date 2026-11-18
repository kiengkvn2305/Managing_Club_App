<?php

namespace App\Models;

use App\Enums\ClubRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'slug', 'description', 'city', 'home_ground', 'format', 'formats', 'status'];

    protected function casts(): array { return ['formats' => 'array']; }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClubMembership::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(PlayerProfile::class);
    }

    public function roleFor(User $user): ?ClubRole
    {
        return $this->memberships()->where('user_id', $user->id)->where('status', 'active')->first()?->role;
    }
}
