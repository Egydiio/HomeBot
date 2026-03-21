<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'name',
        'value', 'category', 'confirmed'
    ];

    protected $casts = [
        'value'     => 'decimal:2',
        'confirmed' => 'boolean',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
