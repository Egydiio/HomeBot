<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name', 'slug', 'active'];

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function balances()
    {
        return $this->hasMany(Balance::class);
    }

    public function monthlyCloses()
    {
        return $this->hasMany(MonthlyClose::class);
    }
}
