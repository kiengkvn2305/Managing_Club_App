<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function record(Club $club, User $actor, string $action, Model $subject): void
    {
        DB::table('audit_logs')->insert(['club_id' => $club->id, 'actor_id' => $actor->id, 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => $subject->id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
