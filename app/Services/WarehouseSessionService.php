<?php

namespace App\Services;

use App\Models\User;
use App\Models\Warehouse;

class WarehouseSessionService
{
    public static function bind(Warehouse $warehouse): void
    {
        session([
            'active_warehouse_code' => $warehouse->code,
            'active_warehouse_name' => $warehouse->name,
            'active_warehouse_type' => $warehouse->type,
        ]);
        session()->forget('global_mode');
    }

    public static function clear(): void
    {
        session()->forget(['active_warehouse_code', 'active_warehouse_name', 'active_warehouse_type']);
        session(['global_mode' => true]);
    }

    /**
     * Set session gudang sesuai pengikatan user (dipanggil saat login & middleware).
     */
    public static function syncForUser(User $user): void
    {
        // Super Admin dan Admin: jangan auto-bind, biarkan mereka pilih sendiri
        if ($user->isSuperAdmin() || $user->hasRole(User::ROLE_ADMIN)) {
            return;
        }

        if ($user->isWarehouseBound()) {
            $wh = $user->relationLoaded('warehouse')
                ? $user->warehouse
                : Warehouse::where('code', $user->warehouse_code)->first();

            if ($wh) {
                self::bind($wh);
            }

            return;
        }

        self::clear();
    }

    public static function userCanSelectWarehouse(User $user, string $code): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isWarehouseBound()) {
            return $user->warehouse_code === $code;
        }

        return true;
    }
}
