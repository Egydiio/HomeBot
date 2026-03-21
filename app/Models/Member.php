<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'group_id', 'name', 'phone',
        'pix_key', 'split_percent', 'active'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function debts()
    {
        return $this->hasMany(Balance::class, 'debtor_id');
    }

    public function credits()
    {
        return $this->hasMany(Balance::class, 'creditor_id');
    }
}
