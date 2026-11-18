<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return collect($this->resource->getAttributes())->only(['id', 'name', 'slug', 'description', 'city', 'home_ground', 'format', 'status'])->all()
            + ['formats' => $this->resource->formats ?: [$this->resource->format], 'membership_role' => $this->resource->roleFor($request->user())?->value, 'can_view_finance' => $request->user()->can('viewFinance', $this->resource), 'can_manage_finance' => $request->user()->can('manageFinance', $this->resource)];
    }
}
