<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Technician extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'area', 'warehouse_code'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'code');
    }
}
