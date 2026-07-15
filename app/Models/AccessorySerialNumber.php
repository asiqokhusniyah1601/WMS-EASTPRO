<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessorySerialNumber extends Model
{
    protected $fillable = [
        'accessory_code',
        'warehouse_code',
        'serial_number',
        'status',
        'notes',
    ];
}
