<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    public $table='sales';

    protected $guarded = [];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Companies::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
{
    return $this->hasMany(SaleItem::class);
}

}
