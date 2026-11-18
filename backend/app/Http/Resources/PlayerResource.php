<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'club_id' => $this->club_id, 'team_id' => $this->team_id,
            'full_name' => $this->full_name, 'email' => $this->email, 'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'), 'jersey_number' => $this->jersey_number,
            'position' => $this->position, 'preferred_foot' => $this->preferred_foot,
            'status' => $this->status, 'notes' => $this->notes,
            'avatar_version' => ($this->avatar_path || $this->user?->avatar_path) ? hash('sha256', $this->avatar_path ?: $this->user->avatar_path) : null,
            'has_custom_avatar' => (bool) $this->avatar_path,
            'team' => $this->whenLoaded('team', fn () => $this->team ? ['id' => $this->team->id, 'name' => $this->team->name] : null),
            'teams' => $this->whenLoaded('teams', fn () => $this->teams->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'format' => $team->format, 'jersey_number' => $team->pivot->jersey_number, 'position' => $team->pivot->position])),
        ];
    }
}
