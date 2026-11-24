<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherScan extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['scanned_at' => 'datetime'];
}
