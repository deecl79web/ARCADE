<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $guarded = [];

    protected $hidden = ['signature'];

    protected $appends = ['is_expired'];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'purchased'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->belongsTo(VoucherType::class, 'voucher_type_id');
    }

    public function scans()
    {
        return $this->hasMany(VoucherScan::class);
    }
}
