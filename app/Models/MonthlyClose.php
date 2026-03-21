<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyClose extends Model
{
    protected $fillable = [
        'group_id', 'reference_month', 'status',
        'amount', 'debtor_id', 'creditor_id',
        'charged_at', 'paid_at'
    ];

    protected $casts = [
        'reference_month' => 'date',
        'amount'          => 'decimal:2',
        'charged_at'      => 'datetime',
        'paid_at'         => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function debtor()
    {
        return $this->belongsTo(Member::class, 'debtor_id');
    }

    public function creditor()
    {
        return $this->belongsTo(Member::class, 'creditor_id');
    }
}
