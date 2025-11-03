<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'balance';

    // Relationships
    public function company()
    {
        return $this->belongsTo(Companies::class);
    }
}
