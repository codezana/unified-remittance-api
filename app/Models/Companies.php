<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Companies extends Model
{
    use HasFactory;

    protected $guarded = [];
    // Relationships
    public function balances()
    {
        return $this->hasMany(Balance::class);
    }

    public function details()
    {
        return $this->hasMany(Detail::class);
    }
}
