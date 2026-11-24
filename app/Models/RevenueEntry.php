<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount_fcfa' => 'integer',
        'received_on' => 'date:Y-m-d',
    ];
}
