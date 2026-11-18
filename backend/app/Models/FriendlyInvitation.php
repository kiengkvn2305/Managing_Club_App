<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FriendlyInvitation extends Model
{
    protected $fillable = ['source_club_id', 'target_club_id', 'pair_key', 'pending_key', 'status', 'match_date', 'match_time', 'location', 'message', 'created_by', 'responded_by'];
}
