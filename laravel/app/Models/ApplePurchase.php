<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplePurchase extends Model
{
    protected $fillable = [
        'user_id',
        'transaction_id',
        'product_id',
        'environment',
        'purchased_at',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
    ];
}
