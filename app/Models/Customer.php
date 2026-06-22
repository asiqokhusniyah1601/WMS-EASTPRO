<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'contract_no'];

    public function devices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class, 'customer_id');
    }
}
