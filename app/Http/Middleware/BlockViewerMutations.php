<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockViewerMutations
{
    /**
     * Proteksi CRUD berdasarkan role dan sesi gudang aktif:
     *
     * - Super Admin       : bebas CRUD di semua gudang.
     * - Admin             : CRUD hanya jika sesi gudang == gudang home-nya.
     *                       Jika sedang viewing gudang lain → read-only.
     * - PIC               : CRUD hanya di gudang home (sesi selalu = home).
     * - QC                : tidak perlu CRUD umum (menu terbatas di blade).
     * - Teknisi           : tidak perlu CRUD umum (menu terbatas di blade).
     * - Tidak login       : biarkan lewat (ditangani auth middleware).
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // GET / HEAD / OPTIONS selalu aman
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        // Route navigasi, auth, dan setting personal selalu diperbolehkan
        $allowedRoutes = [
            'logout', 'set.warehouse', 'settings.theme',
            'issue.post', 'issue.accept.handover', 'api.handover.accept'
        ];
        if ($request->route() && in_array($request->route()->getName(), $allowedRoutes)) {
            return $next($request);
        }

        $user = $request->user();

        // Belum login → biarkan lewat (auth middleware yang menangani)
        if (!$user) {
            return $next($request);
        }

        $activeCode = session('active_warehouse_code');
        $activeType = strtolower(session('active_warehouse_type') ?? '');

        // Tidak ada sesi gudang aktif → mode Global (view-only)
        if (empty($activeCode)) {
            return $this->rejectMutation($request,
                'Operasi ditolak: Anda sedang dalam mode Global (view-only). Silakan pilih Gudang Cabang.');
        }

        // Sesi Pusat (Regional East / West) → read-only
        if ($activeType === 'pusat' || str_contains($activeCode, '__region_')) {
            return $this->rejectMutation($request,
                'Operasi ditolak: Gudang Global/Regional (East/West) hanya dapat melihat data. Silakan pilih Gudang Cabang.');
        }

        // Super Admin selalu boleh di gudang cabang
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Cek secara absolut apakah Role ini diizinkan melakukan Mutasi (CRUD)
        if (!$user->canMutate()) {
            return $this->rejectMutation($request,
                'Operasi ditolak: Role Anda (' . $user->roleLabel() . ') tidak memiliki akses untuk melakukan perubahan data pada menu ini.');
        }

        // Admin: CRUD hanya boleh di gudang home-nya sendiri
        if ($user->hasRole(\App\Models\User::ROLE_ADMIN)) {
            if ($activeCode !== $user->warehouse_code) {
                return $this->rejectMutation($request,
                    'Operasi ditolak: Anda hanya dapat melakukan CRUD di gudang Anda sendiri (' . $user->warehouse_code . '). Gudang yang sedang aktif adalah view-only.');
            }
        }

        return $next($request);
    }

    private function rejectMutation(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }
        return back()->withErrors(['msg' => 'Aksi diblokir: ' . $message]);
    }
}
