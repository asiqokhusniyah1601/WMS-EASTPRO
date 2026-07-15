<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $roleFilter = $request->input('role');
        $users = User::with('permissions')
            ->when($roleFilter, function ($query, $role) {
                return $query->where('role', $role);
            })
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(10);
            
        $warehouses = Warehouse::orderBy('name')->get();
        $permissions = \Spatie\Permission\Models\Permission::orderBy('name')->get();

        return view('users.index', compact('users', 'warehouses', 'permissions', 'roleFilter'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'       => ['required', 'confirmed', Password::min(6)],
            'role'           => ['required', Rule::in(array_keys(User::ROLES))],
            // Warehouse wajib untuk Admin, PIC, QC, Teknisi
            'warehouse_code' => [
                Rule::requiredIf(fn () => in_array($request->role, [
                    User::ROLE_ADMIN,
                    User::ROLE_PIC,
                    User::ROLE_QC,
                    User::ROLE_TECHNICIAN,
                    User::ROLE_STAFF_GUDANG,
                ])),
                'nullable',
                'string',
            ],
            'technician_area' => [
                Rule::requiredIf(fn () => $request->role === User::ROLE_TECHNICIAN),
                'nullable',
                'string',
                'max:255',
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($validated['role'] === User::ROLE_SUPER_ADMIN) {
            $validated['warehouse_code'] = null;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'warehouse_code' => $validated['warehouse_code'] ?? null,
            'is_active' => true,
        ]);

        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        if ($validated['role'] === User::ROLE_TECHNICIAN) {
            // Auto create technician record
            // Generates a unique code
            $code = 'TECH-' . strtoupper(substr(md5(uniqid()), 0, 6));
            \App\Models\Technician::create([
                'code' => $code,
                'name' => $user->name,
                'area' => $validated['technician_area'],
                'warehouse_code' => $user->warehouse_code,
            ]);
        }

        return redirect()->route('users.index')->with('success', 'Pengguna baru berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'       => ['nullable', 'confirmed', Password::min(6)],
            'role'           => ['required', Rule::in(array_keys(User::ROLES))],
            // Warehouse wajib untuk Admin, PIC, QC, Teknisi
            'warehouse_code' => [
                Rule::requiredIf(fn () => in_array($request->role, [
                    User::ROLE_ADMIN,
                    User::ROLE_PIC,
                    User::ROLE_QC,
                    User::ROLE_TECHNICIAN,
                    User::ROLE_STAFF_GUDANG,
                ])),
                'nullable',
                'string',
            ],
            'technician_area' => [
                Rule::requiredIf(fn () => $request->role === User::ROLE_TECHNICIAN),
                'nullable',
                'string',
                'max:255',
            ],
            'is_active'      => ['nullable', 'boolean'],
            'permissions'    => ['nullable', 'array'],
            'permissions.*'  => ['string', 'exists:permissions,name'],
        ]);

        // Cegah super admin terakhir menonaktifkan / menurunkan dirinya sendiri sehingga sistem terkunci.
        $isSelf = $request->user()->id === $user->id;
        $demotingLastSuperAdmin = $user->isSuperAdmin()
            && $validated['role'] !== User::ROLE_SUPER_ADMIN
            && User::where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->count() <= 1;

        if ($demotingLastSuperAdmin) {
            return redirect()->route('users.index')
                ->withErrors(['role' => 'Tidak bisa menurunkan role Super Admin terakhir yang aktif.']);
        }

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'warehouse_code' => $validated['role'] === User::ROLE_SUPER_ADMIN ? null : ($validated['warehouse_code'] ?? null),
            'is_active' => $isSelf ? true : (bool) ($validated['is_active'] ?? false),
        ];

        if (!empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        } else {
            $user->syncPermissions([]);
        }

        if ($validated['role'] === User::ROLE_TECHNICIAN) {
            $technician = \App\Models\Technician::where('name', $user->name)->first();
            if ($technician) {
                $technician->update([
                    'area' => $validated['technician_area'],
                    'warehouse_code' => $user->warehouse_code,
                ]);
            } else {
                $code = 'TECH-' . strtoupper(substr(md5(uniqid()), 0, 6));
                \App\Models\Technician::create([
                    'code' => $code,
                    'name' => $user->name,
                    'area' => $validated['technician_area'],
                    'warehouse_code' => $user->warehouse_code,
                ]);
            }
        }

        return redirect()->route('users.index')->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return redirect()->route('users.index')
                ->withErrors(['delete' => 'Anda tidak bisa menghapus akun Anda sendiri.']);
        }

        if ($user->isSuperAdmin() && User::where('role', User::ROLE_SUPER_ADMIN)->count() <= 1) {
            return redirect()->route('users.index')
                ->withErrors(['delete' => 'Tidak bisa menghapus Super Admin terakhir.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus.');
    }
}
