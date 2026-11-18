<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ClubMatch extends Model
{
    protected $fillable = ['club_id', 'team_id', 'friendly_invitation_id', 'opponent', 'match_date', 'start_time', 'end_time', 'location', 'match_type', 'status', 'our_score', 'opponent_score', 'notes', 'created_by'];
}
