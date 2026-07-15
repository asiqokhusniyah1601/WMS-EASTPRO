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
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="card-title">Daftar Pengguna ({{ $users->total() }})</div>
                    <form method="GET" action="{{ route('users.index') }}" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                        <label for="roleFilter" style="font-size: 13px; font-weight: 600; margin: 0; color: var(--text-secondary);">Filter Role:</label>
                        <select name="role" id="roleFilter" class="form-control" onchange="this.form.submit()" style="padding: 4px 32px 4px 12px; height: auto; min-height: 32px; font-size: 13px; border-radius: 6px; width: auto;">
                            <option value="">Semua Role</option>
                            @foreach (\App\Models\User::ROLES as $key => $label)
                                <option value="{{ $key }}" {{ request('role') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
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
                                    $technicianArea = '';
                                    if ($u->role === \App\Models\User::ROLE_TECHNICIAN) {
                                        $tech = \App\Models\Technician::where('name', $u->name)->first();
                                        if ($tech) $technicianArea = $tech->area;
                                    }
                                    $userPayload = [
                                        'id' => $u->id,
                                        'name' => $u->name,
                                        'email' => $u->email,
                                        'role' => $u->role,
                                        'warehouse_code' => $u->warehouse_code,
                                        'technician_area' => $technicianArea,
                                        'is_active' => (bool) $u->is_active,
                                        'permissions' => $u->permissions->pluck('name')->toArray(),
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
                <div style="margin-top: 20px; padding: 0 20px 20px; display: flex; justify-content: flex-end;">
                    <style>
                        .pagination { display: flex; padding-left: 0; list-style: none; gap: 4px; margin: 0; }
                        .page-item .page-link { position: relative; display: block; padding: 6px 12px; margin-left: -1px; line-height: 1.25; color: var(--text-primary); background-color: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s ease; }
                        .page-item.active .page-link { z-index: 3; color: #fff; background-color: var(--accent-blue); border-color: var(--accent-blue); }
                        .page-item.disabled .page-link { color: var(--text-muted); pointer-events: none; cursor: auto; background-color: var(--bg-secondary); border-color: var(--border-color); }
                        .page-link:hover { z-index: 2; color: var(--accent-blue); text-decoration: none; background-color: var(--bg-secondary); border-color: var(--border-color); }
                    </style>
                    {{ $users->appends(request()->query())->links('pagination::bootstrap-4') }}
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
                        <select name="role" id="role" class="form-control" onchange="toggleWarehouseField('role', '')" required>
                            @foreach(\App\Models\User::ROLES as $value => $label)
                                <option value="{{ $value }}" {{ old('role') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="warehouse_field_regional" style="display: none;">
                        <label for="warehouse_code_regional">Gudang Pusat (Regional) <span style="color: var(--accent-rose);">*</span></label>
                        <select name="warehouse_code_regional" id="warehouse_code_regional" class="form-control" onchange="document.getElementById('real_warehouse_code').value = this.value">
                            <option value="">— Pilih Gudang Pusat —</option>
                            @foreach($warehouses->where('type', 'PUSAT') as $wh)
                                <option value="{{ $wh->code }}" {{ old('warehouse_code') === $wh->code ? 'selected' : '' }}>
                                    {{ $wh->name }} ({{ $wh->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    


                    <div class="form-group" id="warehouse_field_branch" style="display: none;">
                        <label for="warehouse_code_branch">Gudang Cabang <span style="color: var(--accent-rose);">*</span></label>
                        <select name="warehouse_code_branch" id="warehouse_code_branch" class="form-control" onchange="document.getElementById('real_warehouse_code').value = this.value">
                            <option value="">— Pilih Gudang Cabang —</option>
                            @foreach($warehouses->where('type', 'CABANG') as $wh)
                                <option value="{{ $wh->code }}" {{ old('warehouse_code') === $wh->code ? 'selected' : '' }}>
                                    {{ $wh->name }} ({{ $wh->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" id="warehouse_field_all" style="display: none;">
                        <label for="warehouse_code_all">Gudang <span style="color: var(--accent-rose);">*</span></label>
                        <select name="warehouse_code_all" id="warehouse_code_all" class="form-control" onchange="document.getElementById('real_warehouse_code').value = this.value">
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->code }}" {{ old('warehouse_code') === $wh->code ? 'selected' : '' }}>
                                    {{ $wh->name }} ({{ $wh->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group" id="technician_area_field" style="display: none;">
                        <label for="technician_area">Area / Kota Kerja (Khusus Teknisi) <span style="color: var(--accent-rose);">*</span></label>
                        <input type="text" name="technician_area" id="technician_area" class="form-control" value="{{ old('technician_area') }}" placeholder="Contoh: Malang Selatan">
                    </div>

                    <div class="form-group">
                        <label>Hak Akses Tambahan (Opsional)</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 5px;">
                            @foreach($permissions as $perm)
                                <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}" style="accent-color: var(--accent-blue);">
                                    <span style="font-size: 14px; text-transform: capitalize;">{{ $perm->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <input type="hidden" name="warehouse_code" id="real_warehouse_code" value="{{ old('warehouse_code') }}">
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

    <div id="editUserModal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div class="card" style="width: 100%; max-width: 440px; max-height: 90vh; overflow-y: auto; overflow-x: hidden; scroll-behavior: smooth; scrollbar-width: thin; margin: 0;">
            <div class="card-header" style="display:flex; justify-content: flex-start; align-items: center; position: sticky; top: 0; background: var(--bg-primary, #fff); z-index: 10;">
                <div class="card-title"><i class="fa-solid fa-user-pen"></i> Edit Pengguna</div>
            </div>
            <form id="editUserForm" method="POST" style="padding-top: 10px;">
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
                    <select name="role" id="edit_role" class="form-control" onchange="toggleWarehouseField('edit_role', 'edit_')" required>
                        @foreach(\App\Models\User::ROLES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group" id="edit_warehouse_field_regional" style="display: none;">
                    <label for="edit_warehouse_code_regional">Gudang Pusat (Regional) <span style="color: var(--accent-rose);">*</span></label>
                    <select name="warehouse_code_regional" id="edit_warehouse_code_regional" class="form-control" onchange="document.getElementById('edit_real_warehouse_code').value = this.value">
                        <option value="">— Pilih Gudang Pusat —</option>
                        @foreach($warehouses->where('type', 'PUSAT') as $wh)
                            <option value="{{ $wh->code }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                

                <div class="form-group" id="edit_warehouse_field_branch" style="display: none;">
                    <label for="edit_warehouse_code_branch">Gudang Cabang <span style="color: var(--accent-rose);">*</span></label>
                    <select name="warehouse_code_branch" id="edit_warehouse_code_branch" class="form-control" onchange="document.getElementById('edit_real_warehouse_code').value = this.value">
                        <option value="">— Pilih Gudang Cabang —</option>
                        @foreach($warehouses->where('type', 'CABANG') as $wh)
                            <option value="{{ $wh->code }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="edit_warehouse_field_all" style="display: none;">
                    <label for="edit_warehouse_code_all">Gudang <span style="color: var(--accent-rose);">*</span></label>
                    <select name="warehouse_code_all" id="edit_warehouse_code_all" class="form-control" onchange="document.getElementById('edit_real_warehouse_code').value = this.value">
                        <option value="">— Pilih Gudang —</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->code }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="edit_technician_area_field" style="display: none;">
                    <label for="edit_technician_area">Area / Kota Kerja (Khusus Teknisi) <span style="color: var(--accent-rose);">*</span></label>
                    <input type="text" name="technician_area" id="edit_technician_area" class="form-control" placeholder="Contoh: Malang Selatan">
                </div>

                <div class="form-group">
                    <label>Hak Akses Tambahan (Opsional)</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 5px;">
                        @foreach($permissions as $perm)
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->name }}" style="accent-color: var(--accent-blue);">
                                <span style="font-size: 14px; text-transform: capitalize;">{{ $perm->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <input type="hidden" name="warehouse_code" id="edit_real_warehouse_code">
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
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="button" class="btn btn-outline" style="width: 50%; justify-content: center;" onclick="closeEditUser()">
                        <i class="fa-solid fa-xmark"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary" style="width: 50%; justify-content: center;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirm Cancel Modal -->
    <div id="confirmCancelModal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1050; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease;">
        <div class="card" style="width: 100%; max-width: 350px; text-align: center; padding: 20px; transform: scale(0.8); transition: transform 0.3s ease; margin: 20px;">
            <div style="font-size: 40px; color: #f43f5e; margin-bottom: 15px;">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <h3 style="margin-bottom: 10px; font-size: 18px; font-weight: 600;">Batalkan Edit?</h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 14px;">Apakah Anda yakin ingin membatalkan edit?<br>Perubahan yang belum disimpan akan hilang.</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-outline" style="flex: 1; justify-content: center;" onclick="hideConfirmCancel()">Tidak</button>
                <button type="button" class="btn btn-primary" style="flex: 1; justify-content: center; background-color: #f43f5e; border-color: #f43f5e;" onclick="executeCancelEdit()">Ya, Batalkan</button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const userUpdateUrlBase = "{{ url('/users') }}";

    function toggleWarehouseField(roleSelectId, prefixId = '') {
        const role = document.getElementById(roleSelectId).value;
        const isEdit = prefixId === 'edit_';
        
        const fRegional   = document.getElementById(isEdit ? 'edit_warehouse_field_regional'   : 'warehouse_field_regional');
        const fBranch     = document.getElementById(isEdit ? 'edit_warehouse_field_branch'     : 'warehouse_field_branch');
        const fAll        = document.getElementById(isEdit ? 'edit_warehouse_field_all'        : 'warehouse_field_all');
        const fArea       = document.getElementById(isEdit ? 'edit_technician_area_field'      : 'technician_area_field');
        const hiddenWhCode = document.getElementById(isEdit ? 'edit_real_warehouse_code' : 'real_warehouse_code');

        if(fRegional)   fRegional.style.display   = 'none';
        if(fBranch)     fBranch.style.display     = 'none';
        if(fAll)        fAll.style.display        = 'none';
        if(fArea)       fArea.style.display       = 'none';

        if (role === 'qc' || role === 'pic') {
            if(fBranch) fBranch.style.display = 'block';
            const sel = document.getElementById(isEdit ? 'edit_warehouse_code_branch' : 'warehouse_code_branch');
            if(hiddenWhCode && sel) hiddenWhCode.value = sel.value;
        } else if (role === 'technician') {
            if(fBranch) fBranch.style.display = 'block';
            if(fArea)   fArea.style.display   = 'block';
            const sel = document.getElementById(isEdit ? 'edit_warehouse_code_branch' : 'warehouse_code_branch');
            if(hiddenWhCode && sel) hiddenWhCode.value = sel.value;
        } else if (role !== 'super_admin') {
            if(fAll) fAll.style.display = 'block';
            const sel = document.getElementById(isEdit ? 'edit_warehouse_code_all' : 'warehouse_code_all');
            if(hiddenWhCode && sel) hiddenWhCode.value = sel.value;
        } else {
            if(hiddenWhCode) hiddenWhCode.value = '';
        }
    }

    function openEditUser(user) {
        const form = document.getElementById('editUserForm');
        form.action = userUpdateUrlBase + '/' + user.id;
        document.getElementById('edit_name').value = user.name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_real_warehouse_code').value = user.warehouse_code || '';
        
        // set the value for all possible selects
        if(document.getElementById('edit_warehouse_code_regional'))  document.getElementById('edit_warehouse_code_regional').value  = user.warehouse_code || '';
        if(document.getElementById('edit_warehouse_code_branch'))     document.getElementById('edit_warehouse_code_branch').value     = user.warehouse_code || '';
        if(document.getElementById('edit_warehouse_code_all'))        document.getElementById('edit_warehouse_code_all').value        = user.warehouse_code || '';
        if(document.getElementById('edit_technician_area')) document.getElementById('edit_technician_area').value = user.technician_area || '';
        
        document.querySelectorAll('#editUserForm input[name="permissions[]"]').forEach(cb => cb.checked = false);
        if (user.permissions) {
            user.permissions.forEach(perm => {
                const cb = document.querySelector(`#editUserForm input[name="permissions[]"][value="${perm}"]`);
                if (cb) cb.checked = true;
            });
        }

        document.getElementById('edit_is_active').checked = !!user.is_active;
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_password_confirmation').value = '';

        // Jika mengedit diri sendiri, kunci status aktif agar tidak mengunci diri.
        const isSelf = user.id === {{ auth()->id() }};
        document.getElementById('edit_active_wrap').style.display = isSelf ? 'none' : 'flex';

        toggleWarehouseField('edit_role', 'edit_');
        document.getElementById('editUserModal').style.display = 'flex';
    }

    function closeEditUser() {
        const modal = document.getElementById('confirmCancelModal');
        const card = modal.querySelector('.card');
        modal.style.display = 'flex';
        // Trigger reflow for transition
        void modal.offsetWidth;
        modal.style.opacity = '1';
        card.style.transform = 'scale(1)';
    }

    function hideConfirmCancel() {
        const modal = document.getElementById('confirmCancelModal');
        const card = modal.querySelector('.card');
        modal.style.opacity = '0';
        card.style.transform = 'scale(0.8)';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }

    function executeCancelEdit() {
        hideConfirmCancel();
        document.getElementById('editUserModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        toggleWarehouseField('role', '');
    });
</script>
@endsection
