<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceModel extends Model
{
    use HasFactory;

    protected $table = 'device_models';

    protected $fillable = [
        'brand',
        'type',
        'model'
    ];
}
