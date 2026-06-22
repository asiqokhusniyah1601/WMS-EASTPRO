<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GsmSimcard extends Model
{
    protected $fillable = ['msisdn', 'provider', 'category', 'status', 'warehouse_code'];

    public function device(): HasOne
    {
        return $this->hasOne(Device::class, 'gsm_simcard_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SimcardTransaction::class, 'gsm_simcard_id');
    }
}
