<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlannedExpense extends Model
{
    protected $fillable = ['club_id', 'description', 'amount', 'notes', 'effective_from', 'effective_to', 'cancelled', 'created_by'];
}
