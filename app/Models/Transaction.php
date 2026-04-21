<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'group_id', 'member_id', 'type', 'description',
        'total_amount', 'house_amount', 'receipt_image',
        'status', 'reference_month'
    ];

    protected $casts = [
        'reference_month' => 'date',
        'total_amount'    => 'decimal:2',
        'house_amount'    => 'decimal:2',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
