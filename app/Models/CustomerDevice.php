<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDevice extends Model
{
    protected $fillable = ['customer_id', 'device_id', 'installed_at', 'uninstalled_at'];

    protected $casts = [
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
