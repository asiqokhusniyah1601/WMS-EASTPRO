@extends('layouts.app')

@section('title', 'Manajemen Pengguna | DLMS')

@section('content')
    <div class="animate-fade-in">
        <x-page-header
            icon="fa-users-gear"
            title="Manajemen Pengguna & Role"
            subtitle="Kelola akun, atur role akses, dan tautkan Admin Gudang ke gudang tertentu. Hanya Super Admin yang dapat mengakses halaman ini." />

        @if ($errors->any())
            <div class="alert-box alert-danger animate-fade-in" style="margin-bottom: 20px;">
                <div class="alert-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                <div class="alert-message">
                    <ul style="margin: 0; padding-left: 15px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
            <!-- User List -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Daftar Pengguna ({{ $users->count() }})</div>
                </div>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Gudang</th>
                                <th>Status</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $u)
                                @php
                                    $userPayload = [
                                        'id' => $u->id,
                                        'name' => $u->name,
                                        'email' => $u->email,
                                        'role' => $u->role,
                                        'warehouse_code' => $u->warehouse_code,
                                        'is_active' => (bool) $u->is_active,
                                    ];
                                @endphp
                                <tr>
                                    <td style="font-weight: 600;">{{ $u->name }}</td>
                                    <td style="color: var(--text-secondary);">{{ $u->email }}</td>
                                    <td>
                                        @if($u->isSuperAdmin())
                                            <span class="badge badge-info"><i class="fa-solid fa-shield-halved"></i> {{ $u->roleLabel() }}</span>
                                        @else
                                            <span class="badge badge-success"><i class="fa-solid fa-warehouse"></i> {{ $u->roleLabel() }}</span>
                                        @endif
                                    </td>
                                    <td style="font-family: monospace; font-size: 12px; color: var(--text-muted);">
                                        {{ $u->warehouse_code ?? '—' }}
                                    </td>
                                    <td>
                                        @if($u->is_active)
                                            <span class="badge badge-success">Aktif</span>
                                        @else
                                            <span class="badge badge-warning">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" class="btn btn-outline btn-icon-sm"
                                            title="Edit pengguna"
                                            onclick='openEditUser(@json($userPayload))'>
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        @if($u->id !== auth()->id())
                                            <form action="{{ route('users.destroy', $u) }}" method="POST" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-icon-sm"
                                                    onclick="return confirm('Hapus pengguna {{ $u->name }}?')" title="Hapus">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add User -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-user-plus"></i> Tambah Pengguna</div>
                </div>
                <form action="{{ route('users.store') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}"
                            placeholder="Contoh: Budi Santoso" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}"
                            placeholder="nama@perusahaan.com" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select name="role" id="role" class="form-control" onchange="toggleWarehouseField('role', 'warehouse_field')" required>
                            @foreach(\App\Models\User::ROLES as $value => $label)
                                <option value="{{ $value }}" {{ old('role') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="warehouse_field">
                        <label for="warehouse_code">Gudang (untuk Admin Gudang)</label>
                        <select name="warehouse_code" id="warehouse_code" class="form-control">
                            <option value="">— Tidak ditautkan —</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->code }}" {{ old('warehouse_code') === $wh->code ? 'selected' : '' }}>
                                    {{ $wh->name }} ({{ $wh->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Minimal 6 karakter" required>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                            class="form-control" placeholder="Ulangi password" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Pengguna
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1000; align-items: center; justify-content: center;">
        <div class="card" style="width: 100%; max-width: 440px; margin: 20px;">
            <div class="card-header" style="display:flex; justify-content: space-between; align-items: center;">
                <div class="card-title"><i class="fa-solid fa-user-pen"></i> Edit Pengguna</div>
                <button type="button" class="btn btn-icon-sm btn-outline" onclick="closeEditUser()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="editUserForm" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="edit_name">Nama Lengkap</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_role">Role</label>
                    <select name="role" id="edit_role" class="form-control" onchange="toggleWarehouseField('edit_role', 'edit_warehouse_field')" required>
                        @foreach(\App\Models\User::ROLES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" id="edit_warehouse_field">
                    <label for="edit_warehouse_code">Gudang (untuk Admin Gudang)</label>
                    <select name="warehouse_code" id="edit_warehouse_code" class="form-control">
                        <option value="">— Tidak ditautkan —</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->code }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_password">Password Baru <span style="color: var(--text-muted); font-weight: 400;">(kosongkan jika tidak diubah)</span></label>
                    <input type="password" name="password" id="edit_password" class="form-control" placeholder="Minimal 6 karakter">
                </div>
                <div class="form-group">
                    <label for="edit_password_confirmation">Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation" id="edit_password_confirmation" class="form-control">
                </div>
                <label class="form-group" id="edit_active_wrap" style="display:flex; align-items:center; gap: 8px; cursor:pointer;">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width:16px;height:16px;accent-color:var(--accent-blue);">
                    <span>Akun aktif (bisa login)</span>
                </label>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const userUpdateUrlBase = "{{ url('/users') }}";

    function toggleWarehouseField(roleSelectId, fieldId) {
        const role = document.getElementById(roleSelectId).value;
        const field = document.getElementById(fieldId);
        field.style.display = role === 'super_admin' ? 'none' : 'block';
    }

    function openEditUser(user) {
        const form = document.getElementById('editUserForm');
        form.action = userUpdateUrlBase + '/' + user.id;
        document.getElementById('edit_name').value = user.name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_warehouse_code').value = user.warehouse_code || '';
        document.getElementById('edit_is_active').checked = !!user.is_active;
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_password_confirmation').value = '';

        // Jika mengedit diri sendiri, kunci status aktif agar tidak mengunci diri.
        const isSelf = user.id === {{ auth()->id() }};
        document.getElementById('edit_active_wrap').style.display = isSelf ? 'none' : 'flex';

        toggleWarehouseField('edit_role', 'edit_warehouse_field');
        document.getElementById('editUserModal').style.display = 'flex';
    }

    function closeEditUser() {
        document.getElementById('editUserModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        toggleWarehouseField('role', 'warehouse_field');
    });
</script>
@endsection
