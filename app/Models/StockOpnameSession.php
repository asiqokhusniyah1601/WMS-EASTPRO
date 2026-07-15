<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpnameSession extends Model
{
    protected $fillable = [
        'warehouse_code', 'opname_date', 'status', 'started_by',
        'completed_at', 'crosscheck_result', 'notes',
    ];

    protected $casts = [
        'completed_at'      => 'datetime',
        'crosscheck_result' => 'array',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'code');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
