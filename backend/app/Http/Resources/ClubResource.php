<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return collect($this->resource->getAttributes())->only(['id', 'name', 'slug', 'description', 'city', 'home_ground', 'format', 'status'])->all()
            + ['formats' => $this->resource->formats ?: [$this->resource->format], 'is_discoverable' => (bool) $this->resource->is_discoverable, 'avatar_version' => $this->resource->avatar_path ? hash('sha256', $this->resource->avatar_path) : null, 'background_version' => $this->resource->background_path ? hash('sha256', $this->resource->background_path) : null, 'membership_role' => $this->resource->roleFor($request->user())?->value, 'can_view_finance' => $request->user()->can('viewFinance', $this->resource), 'can_manage_finance' => $request->user()->can('manageFinance', $this->resource), 'can_manage_activities' => $request->user()->can('manageActivities', $this->resource), 'can_view_inventory' => $request->user()->can('viewInventory', $this->resource), 'can_manage_inventory' => $request->user()->can('manageInventory', $this->resource)];
    }
}
