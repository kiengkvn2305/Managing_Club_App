<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubPoll extends Model
{
    protected $fillable = ['club_id', 'created_by', 'title', 'description', 'deadline'];
    protected function casts(): array { return ['deadline' => 'datetime']; }
}
