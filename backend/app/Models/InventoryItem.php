<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = ['club_id', 'name', 'category', 'unit', 'kind', 'quantity', 'low_stock_threshold', 'notes', 'archived'];
    protected $casts = ['quantity' => 'integer', 'low_stock_threshold' => 'integer', 'archived' => 'boolean'];
}
