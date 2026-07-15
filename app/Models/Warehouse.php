<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'type', 'region'];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'warehouse_code', 'code');
    }
}
