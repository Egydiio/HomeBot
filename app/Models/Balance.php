<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    protected $fillable = [
        'group_id', 'debtor_id', 'creditor_id',
        'amount', 'reference_month'
    ];

    protected $casts = [
        'reference_month' => 'date',
        'amount'          => 'decimal:2',
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
