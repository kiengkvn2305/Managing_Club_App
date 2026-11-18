<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceTransaction extends Model
{
    protected $fillable = ['club_id', 'created_by', 'type', 'amount', 'occurred_on', 'description', 'receipt_path'];
    protected $hidden = ['receipt_path'];
}
