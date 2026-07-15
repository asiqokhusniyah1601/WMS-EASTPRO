<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    /**
     * Batasi akses route hanya untuk role tertentu.
     * Penggunaan: ->middleware('role:super_admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasRole(...$roles)) {
            if ($request->expectsJson()) {
                abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
            }

            return redirect()
                ->route('dashboard')
                ->withErrors(['msg' => 'Anda tidak memiliki hak akses untuk halaman ini. Menu Manajemen Pengguna hanya tersedia untuk Super Admin.']);
        }

        return $next($request);
    }
}
