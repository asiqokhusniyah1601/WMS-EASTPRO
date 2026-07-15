<?php

namespace App\Http\Middleware;

use App\Services\WarehouseSessionService;
use Closure;
use Illuminate\Http\Request;

class EnsureWarehouseSelected
{
    /**
     * Routes yang boleh diakses tanpa warehouse session.
     */
    protected array $except = [
        'select-warehouse',
        'set-warehouse',
    ];

    public function handle(Request $request, Closure $next)
    {
        foreach ($this->except as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        if ($request->is('css/*', 'js/*', 'uploads/*', 'favicon*')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Super Admin: mode Global (tanpa session gudang) diperbolehkan.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Admin: harus memilih gudang kerja terlebih dahulu sebelum masuk dashboard.
        if ($user->hasRole(\App\Models\User::ROLE_ADMIN)) {
            $whCode = session('active_warehouse_code');
            if (!$whCode && !session()->has('global_mode')) {
                return redirect()->route('select.warehouse');
            }
            return $next($request);
        }

        // PIC / QC / Teknisi terikat: auto-set session ke gudang miliknya.
        if ($user->isWarehouseBound()) {
            WarehouseSessionService::syncForUser($user);
            return $next($request);
        }

        // Fallback aman: user lama tanpa warehouse_code → arahkan pilih gudang.
        if (!session()->has('active_warehouse_code')) {
            return redirect()->route('select.warehouse');
        }

        return $next($request);
    }
}
