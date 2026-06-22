<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimcardTransaction extends Model
{
    protected $fillable = [
        'gsm_simcard_id',
        'msisdn',
        'action',
        'from_location',
        'to_location',
        'warehouse_code',
        'operator',
        'notes',
    ];

    public function simcard(): BelongsTo
    {
        return $this->belongsTo(GsmSimcard::class, 'gsm_simcard_id');
    }
}
