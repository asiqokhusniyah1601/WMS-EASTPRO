<?php

namespace App\Http\Middleware;

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
        // Allow warehouse selection routes
        foreach ($this->except as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        // Allow API / asset requests
        if ($request->is('css/*', 'js/*', 'uploads/*', 'favicon*')) {
            return $next($request);
        }

        // Check session
        if (!session()->has('active_warehouse_code')) {
            return redirect()->route('select.warehouse');
        }

        return $next($request);
    }
}
