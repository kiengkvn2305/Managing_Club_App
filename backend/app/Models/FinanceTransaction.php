<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceTransaction extends Model
{
    protected $fillable = ['club_id', 'created_by', 'type', 'amount', 'occurred_on', 'description', 'receipt_path', 'category', 'paid_by', 'notes'];
    protected $hidden = ['receipt_path'];
}
