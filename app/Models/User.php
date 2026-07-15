<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'warehouse_code', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    // =========================================================
    // ROLE CONSTANTS
    // =========================================================
    /** Akses penuh ke seluruh sistem. */
    public const ROLE_SUPER_ADMIN = 'super_admin';

    /**
     * Admin Gudang: akses penuh CRUD di gudang yang di-assign,
     * PLUS bisa melihat (view-only) stok gudang lain.
     */
    public const ROLE_ADMIN = 'admin';

    /**
     * PIC Gudang: CRUD hanya di gudang yang di-assign,
     * tidak bisa melihat gudang lain.
     */
    public const ROLE_PIC = 'pic';

    /**
     * QC: hanya dapat mengakses menu Quality Control,
     * di Warehouse Pusat (East atau West) yang di-assign.
     */
    public const ROLE_QC = 'qc';

    /** Teknisi: hanya dapat mengakses halaman Serah Terima. */
    public const ROLE_TECHNICIAN = 'technician';

    /** Staff Gudang: hanya dapat mengakses halaman Stock Opname (scan barcode). */
    public const ROLE_STAFF_GUDANG = 'staff_gudang';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN  => 'Super Admin',
        self::ROLE_ADMIN        => 'Admin',
        self::ROLE_PIC          => 'PIC',
        self::ROLE_QC           => 'QC',
        self::ROLE_TECHNICIAN   => 'Teknisi',
        self::ROLE_STAFF_GUDANG => 'Staff Gudang',
    ];

    // =========================================================
    // CASTS
    // =========================================================
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // =========================================================
    // HELPERS
    // =========================================================

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** User yang terikat ke satu gudang (PIC, QC, Teknisi). Super Admin & Admin tidak terikat kaku. */
    public function isWarehouseBound(): bool
    {
        return !$this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN) && !empty($this->warehouse_code);
    }

    /**
     * Boleh membuka halaman "Pilih Gudang Kerja"?
     * - Super Admin: ya (bebas pilih semua)
     * - Admin: ya (bisa view-only ke gudang lain)
     * - PIC / QC / Teknisi: tidak (langsung ke gudang yg di-assign)
     */
    public function canSelectWarehouse(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN);
    }

    /**
     * Apakah user ini boleh melakukan aksi CRUD pada gudang aktif?
     * - Super Admin: selalu ya
     * - Admin: ya hanya jika session gudang == gudang home-nya
     * - PIC: ya hanya di gudang yg di-assign (session selalu sama dengan home)
     * - QC / Teknisi: tidak (akses menu terbatas, bukan CRUD stok umum)
     */
    public function canMutate(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_PIC);
    }

    /** Dapatkan region berdasarkan warehouse yang terikat. */
    public function getRegion(): ?string
    {
        return $this->warehouse?->region;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role));
    }

    // =========================================================
    // RELATIONS
    // =========================================================
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_code', 'code');
    }
}
