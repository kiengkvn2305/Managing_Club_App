<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlayerProfile extends Model
{
    protected $fillable = ['team_id', 'full_name', 'email', 'phone', 'date_of_birth', 'jersey_number', 'position', 'preferred_foot', 'status', 'notes'];

    protected $hidden = ['current_jersey', 'avatar_path'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date:Y-m-d'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_players', 'player_id', 'team_id')->withPivot(['jersey_number', 'position']);
    }
}
