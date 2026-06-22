@extends('layouts.app')

<!--@yield('title', 'Master Data Management | DLMS')-->

@section('content')
    <div class="animate-fade-in">
        <x-page-header
            icon="fa-database"
            title="Pengelolaan Data Master"
            subtitle="Tambah, ubah, hapus, atau import massal data gudang, teknisi, kartu SIM, dan aksesoris." />

        <!-- Master Tabs -->
        <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px;">
            <button class="btn btn-outline active-tab-btn" id="tabWhBtn"
                style="border-bottom: 2px solid var(--accent-blue); border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-primary);">
                <i class="fa-solid fa-warehouse" style="color: var(--accent-blue);"></i> 1. Gudang (Warehouses)
            </button>
            <button class="btn btn-outline" id="tabDeviceModelBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-microchip" style="color: var(--accent-emerald);"></i> 2. Device Models
            </button>
            <button class="btn btn-outline" id="tabAccBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-toolbox" style="color: var(--accent-amber);"></i> 3. Aksesoris (Accessories)
            </button>
            <button class="btn btn-outline" id="tabSimBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-sim-card" style="color: var(--accent-rose);"></i> 4. GSM SIM Cards
            </button>
            <button class="btn btn-outline" id="tabTechBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-user-tie" style="color: var(--accent-indigo);"></i> 5. Teknisi (Technicians)
            </button>
            <button class="btn btn-outline" id="tabCustomerBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-users" style="color: var(--accent-indigo);"></i> 6. Pelanggan (Customer)
            </button>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: WAREHOUSES PANEL -->
        <!-- ========================================== -->
        <div id="panelWh" class="tab-panel">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                <!-- Warehouse List -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Daftar Gudang Terdaftar</div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kode Gudang</th>
                                    <th>Nama Gudang</th>
                                    <th>Tipe</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($warehouses as $wh)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--accent-blue);">{{ $wh['code'] }}</td>
                                        <td>{{ $wh['name'] }}</td>
                                        <td><span class="badge badge-info">{{ $wh['type'] ?? 'CABANG' }}</span></td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editWarehouse('{{ $wh['code'] }}', '{{ $wh['name'] }}', '{{ $wh['type'] ?? 'CABANG' }}')"><i
                                                    class="fa-solid fa-pen"></i></button>
                                            <form action="{{ route('master_data.warehouse.delete', $wh['code']) }}"
                                                method="POST" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-icon-sm"
                                                    onclick="return confirm('Hapus gudang ini?')"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add/Edit & Import Forms -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title" id="whFormTitle">Tambah Gudang</div>
                        </div>
                        <form action="{{ route('master_data.warehouse.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="tab" value="warehouse">
                            <div class="form-group">
                                <label for="wh_code">Kode Gudang</label>
                                <input type="text" name="code" id="wh_code" class="form-control"
                                    placeholder="Contoh: WH-PUSAT" required>
                            </div>
                            <div class="form-group">
                                <label for="wh_name">Nama Gudang</label>
                                <input type="text" name="name" id="wh_name" class="form-control"
                                    placeholder="Contoh: Warehouse Pusat" required>
                            </div>
                            <div class="form-group">
                                <label for="wh_type">Tipe Gudang</label>
                                <select name="type" id="wh_type" class="form-control">
                                    <option value="CABANG">CABANG</option>
                                    <option value="REGIONAL">REGIONAL</option>
                                    <option value="PUSAT">PUSAT</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">Simpan Gudang</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Bulk Import Gudang (CSV)</div>
                            <a href="{{ route('master_data.sample_csv', 'warehouse') }}" class="btn btn-outline btn-icon-sm"
                                title="Download Sample CSV"><i class="fa-solid fa-download"></i> Sample</a>
                        </div>
                        <form action="{{ route('master_data.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="type" value="warehouse">
                            <input type="hidden" name="tab" value="warehouse">
                            <div class="form-group">
                                <label>File CSV (Format: code,name,type)</label>
                                <input type="file" name="file" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline" style="width: 100%; justify-content: center;"><i
                                    class="fa-solid fa-file-import"></i> Upload & Import</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: TECHNICIANS PANEL -->
        <!-- ========================================== -->
        <div id="panelTech" class="tab-panel" style="display: none;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                <!-- Technicians List -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Daftar Teknisi Lapangan</div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID Teknisi</th>
                                    <th>Nama Lengkap</th>
                                    <th>Area</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($technicians as $tech)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--accent-indigo);">{{ $tech['code'] }}</td>
                                        <td>{{ $tech['name'] }}</td>
                                        <td>
                                            @if(!empty($tech['area']))
                                                <span class="badge badge-info"><i class="fa-solid fa-location-dot"></i> {{ $tech['area'] }}</span>
                                            @else
                                                <span style="color: var(--text-muted);">-</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editTechnician('{{ $tech['code'] }}', @js($tech['name']), @js($tech['area'] ?? ''))"><i
                                                    class="fa-solid fa-pen"></i></button>
                                            <form action="{{ route('master_data.technician.delete', $tech['code']) }}"
                                                method="POST" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-icon-sm"
                                                    onclick="return confirm('Hapus teknisi ini?')"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Forms -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title" id="techFormTitle">Tambah Teknisi</div>
                        </div>
                        <form action="{{ route('master_data.technician.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="tab" value="technician">
                            <div class="form-group">
                                <label for="tech_code">ID / Kode Teknisi</label>
                                <input type="text" name="code" id="tech_code" class="form-control"
                                    placeholder="Contoh: TECH-01" required>
                            </div>
                            <div class="form-group">
                                <label for="tech_name">Nama Lengkap</label>
                                <input type="text" name="name" id="tech_name" class="form-control"
                                    placeholder="Contoh: Budi Santoso" required>
                            </div>
                            <div class="form-group">
                                <label for="tech_area">Area / Wilayah</label>
                                <input type="text" name="area" id="tech_area" class="form-control"
                                    placeholder="Contoh: Malang, Kediri, Jember..." list="techAreaList">
                                <datalist id="techAreaList">
                                    @foreach(collect($technicians)->pluck('area')->filter()->unique()->sort() as $areaOpt)
                                        <option value="{{ $areaOpt }}"></option>
                                    @endforeach
                                </datalist>
                                <small style="color: var(--text-muted); font-size: 11px;">Untuk memantau stok per area (teknisi tanpa kantor cabang).</small>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">Simpan Teknisi</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Bulk Import Teknisi (CSV)</div>
                            <a href="{{ route('master_data.sample_csv', 'technician') }}"
                                class="btn btn-outline btn-icon-sm" title="Download Sample CSV"><i
                                    class="fa-solid fa-download"></i> Sample</a>
                        </div>
                        <form action="{{ route('master_data.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="type" value="technician">
                            <input type="hidden" name="tab" value="technician">
                            <div class="form-group">
                                <label>File CSV (Format: code,name,area)</label>
                                <input type="file" name="file" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline" style="width: 100%; justify-content: center;"><i
                                    class="fa-solid fa-file-import"></i> Upload & Import</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: ACCESSORIES PANEL -->
        <!-- ========================================== -->
        <div id="panelAcc" class="tab-panel" style="display: none;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                <!-- Accessories List -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Daftar Aksesoris & Stok</div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kode Aksesoris</th>
                                    <th>Nama Barang</th>
                                    <th>Jumlah Stok (pcs)</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accessories as $acc)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--accent-amber);">{{ $acc['code'] }}</td>
                                        <td>{{ $acc['name'] }}</td>
                                        <td><span class="badge badge-success">{{ $acc['qty'] }} pcs</span></td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editAccessory('{{ $acc['code'] }}', '{{ $acc['name'] }}', {{ $acc['qty'] }})"><i
                                                    class="fa-solid fa-pen"></i></button>
                                            <form action="{{ route('master_data.accessory.delete', $acc['code']) }}"
                                                method="POST" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-icon-sm"
                                                    onclick="return confirm('Hapus aksesoris ini?')"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Forms -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title" id="accFormTitle">Tambah Aksesoris</div>
                        </div>
                        <form action="{{ route('master_data.accessory.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="tab" value="accessory">
                            <div class="form-group">
                                <label for="acc_code">Kode Aksesoris</label>
                                <input type="text" name="code" id="acc_code" class="form-control"
                                    placeholder="Contoh: ACC-CABLE" required>
                            </div>
                            <div class="form-group">
                                <label for="acc_name">Nama Barang</label>
                                <input type="text" name="name" id="acc_name" class="form-control"
                                    placeholder="Contoh: Power Harness Cable" required>
                            </div>
                            <div class="form-group">
                                <label for="acc_qty">Jumlah Stok</label>
                                <input type="number" name="qty" id="acc_qty" min="0" value="0" class="form-control"
                                    required>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">Simpan Aksesoris</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Bulk Import Aksesoris (CSV)</div>
                            <a href="{{ route('master_data.sample_csv', 'accessory') }}" class="btn btn-outline btn-icon-sm"
                                title="Download Sample CSV"><i class="fa-solid fa-download"></i> Sample</a>
                        </div>
                        <form action="{{ route('master_data.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="type" value="accessory">
                            <input type="hidden" name="tab" value="accessory">
                            <div class="form-group">
                                <label>File CSV (Format: code,name,qty)</label>
                                <input type="file" name="file" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline" style="width: 100%; justify-content: center;"><i
                                    class="fa-solid fa-file-import"></i> Upload & Import</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: GSM SIM CARDS PANEL -->
        <!-- ========================================== -->
        <div id="panelSim" class="tab-panel" style="display: none;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                <!-- SIM list -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Daftar GSM SIM Cards</div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>MSISDN / Nomor GSM</th>
                                    <th>Provider</th>
                                    <th>Kategori</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($simcards as $idx => $sim)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td style="font-weight: 600; color: var(--accent-rose);">{{ $sim['msisdn'] }}</td>
                                        <td>{{ $sim['provider'] }}</td>
                                        <td>
                                            @php
                                                $catLower = strtolower($sim['category'] ?? '');
                                                if (str_contains($catLower, 'halo') || str_contains($catLower, 'telkomsel')) {
                                                    $badgeStyle = 'background: rgba(244, 63, 94, 0.12); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.25);';
                                                } elseif (str_contains($catLower, 'b2b')) {
                                                    $badgeStyle = 'background: rgba(16, 185, 129, 0.12); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.25);';
                                                } elseif (str_contains($catLower, 'xl') || str_contains($catLower, 'biz')) {
                                                    $badgeStyle = 'background: rgba(59, 130, 246, 0.12); color: var(--accent-blue); border: 1px solid rgba(59, 130, 246, 0.25);';
                                                } else {
                                                    $badgeStyle = 'background: rgba(99, 102, 241, 0.12); color: var(--accent-indigo); border: 1px solid rgba(99, 102, 241, 0.25);';
                                                }
                                            @endphp
                                            <span class="badge"
                                                style="{{ $badgeStyle }} font-weight: 600; text-transform: none; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.3px;">
                                                {{ $sim['category'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($sim['status'] === 'IN_STOCK')
                                                <span class="badge badge-success">IN STOCK</span>
                                            @elseif($sim['status'] === 'INSTALLED')
                                                <span class="badge badge-info">INSTALLED</span>
                                            @else
                                                <span class="badge badge-danger">{{ $sim['status'] }}</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editSimcard({{ $sim['id'] }}, '{{ $sim['msisdn'] }}', '{{ $sim['provider'] }}', '{{ $sim['category'] ?? '' }}', '{{ $sim['status'] }}')"><i
                                                    class="fa-solid fa-pen"></i></button>
                                            <form action="{{ route('master_data.simcard.delete', $sim['id']) }}" method="POST"
                                                style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-icon-sm"
                                                    onclick="return confirm('Hapus Kartu SIM GSM ini?')"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Forms -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title" id="simFormTitle">Tambah GSM SIM Card</div>
                        </div>
                        <form action="{{ route('master_data.simcard.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="id" id="sim_id">
                            <input type="hidden" name="tab" value="simcard">
                            <div class="form-group">
                                <label for="sim_msisdn">Nomor GSM (MSISDN)</label>
                                <input type="text" name="msisdn" id="sim_msisdn" class="form-control"
                                    placeholder="Contoh: 62811223344" required>
                            </div>
                            <div class="form-group">
                                <label for="sim_provider">Provider Telekomunikasi</label>
                                <input type="text" name="provider" id="sim_provider" class="form-control"
                                    placeholder="Contoh: Telkomsel, XL, Indosat" required>
                            </div>
                            <div class="form-group">
                                <label for="sim_category">Kategori Kartu SIM</label>
                                <input type="text" name="category" id="sim_category" class="form-control"
                                    placeholder="Contoh: Telkomsel Halo, B2B, dll." required>
                            </div>
                            <div class="form-group">
                                <label for="sim_status">Status Kartu SIM</label>
                                <select name="status" id="sim_status" class="form-control">
                                    <option value="IN_STOCK">IN_STOCK</option>
                                    <option value="INSTALLED">INSTALLED</option>
                                    <option value="SUSPENDED">SUSPENDED</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">Simpan Kartu SIM</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Bulk Import GSM Cards (CSV)</div>
                            <a href="{{ route('master_data.sample_csv', 'simcard') }}" class="btn btn-outline btn-icon-sm"
                                title="Download Sample CSV"><i class="fa-solid fa-download"></i> Sample</a>
                        </div>
                        <form action="{{ route('master_data.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="type" value="simcard">
                            <input type="hidden" name="tab" value="simcard">
                            <div class="form-group">
                                <label>File CSV (Format: msisdn,provider,category,status)</label>
                                <input type="file" name="file" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline" style="width: 100%; justify-content: center;"><i
                                    class="fa-solid fa-file-import"></i> Upload & Import</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 5: DEVICE MODELS PANEL -->
        <!-- ========================================== -->
        <div id="panelDeviceModel" class="tab-panel" style="display: none;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                <!-- Device Models list -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Daftar Model Perangkat</div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Merk (Brand)</th>
                                    <th>Tipe (Type)</th>
                                    <th>Model</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($deviceModels as $idx => $dm)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>{{ $dm['brand'] }}</td>
                                        <td><span class="badge badge-info"
                                                style="border-radius: 4px; padding: 4px 8px;">{{ $dm['type'] }}</span></td>
                                        <td style="font-weight: 600; color: var(--accent-emerald);">{{ $dm['model'] }}</td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editDeviceModel({{ $dm['id'] }}, '{{ $dm['brand'] }}', '{{ $dm['type'] }}', '{{ $dm['model'] }}')"><i
                                                    class="fa-solid fa-pen"></i></button>
                                            <form action="{{ route('master_data.device_model.delete', $dm['id']) }}"
                                                method="POST" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-icon-sm"
                                                    onclick="return confirm('Hapus model perangkat ini?')"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Forms -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title" id="dmFormTitle">Tambah Model Perangkat</div>
                        </div>
                        <form action="{{ route('master_data.device_model.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="tab" value="device_model">
                            <div class="form-group">
                                <label for="dm_brand">Merk (Brand)</label>
                                <input type="text" name="brand" id="dm_brand" class="form-control"
                                    placeholder="Contoh: Teltonika, Concox" required>
                            </div>
                            <div class="form-group">
                                <label for="dm_type">Tipe Perangkat</label>
                                <input type="text" name="type" id="dm_type" class="form-control"
                                    placeholder="Contoh: GPS Tracker, MDVR" required>
                            </div>
                            <div class="form-group">
                                <label for="dm_model">Model Perangkat</label>
                                <input type="text" name="model" id="dm_model" class="form-control"
                                    placeholder="Contoh: FMC130, JC400" required>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">Simpan Model</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 6: CUSTOMERS PANEL -->
        <!-- ========================================== -->
        <div id="panelCustomer" class="tab-panel" style="display: none;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                <!-- Customers List -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Daftar Pelanggan (Customer)</div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama Lengkap</th>
                                    <th>Telepon</th>
                                    <th>No. Kontrak</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customers as $cust)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--accent-indigo);">{{ $cust['name'] }}</td>
                                        <td>{{ $cust['phone'] ?? '-' }}</td>
                                        <td>{{ $cust['contract_no'] ?? '-' }}</td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editCustomer({{ $cust['id'] }}, '{{ $cust['name'] }}', '{{ $cust['phone'] }}', '{{ $cust['address'] }}', '{{ $cust['contract_no'] }}')"><i
                                                    class="fa-solid fa-pen"></i></button>
                                            <form action="{{ route('master_data.customer.delete', $cust['id']) }}" method="POST"
                                                style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-icon-sm"
                                                    onclick="return confirm('Hapus customer ini?')"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Forms -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title" id="custFormTitle">Tambah Customer</div>
                        </div>
                        <form action="{{ route('master_data.customer.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="tab" value="customer">
                            <input type="hidden" name="id" id="cust_id">
                            <div class="form-group">
                                <label for="cust_name">Nama Lengkap / Perusahaan</label>
                                <input type="text" name="name" id="cust_name" class="form-control"
                                    placeholder="Contoh: PT ABC / Budi Santoso" required>
                            </div>
                            <div class="form-group">
                                <label for="cust_phone">Nomor Telepon</label>
                                <input type="text" name="phone" id="cust_phone" class="form-control"
                                    placeholder="Contoh: 08123456789">
                            </div>
                            <div class="form-group">
                                <label for="cust_address">Alamat</label>
                                <textarea name="address" id="cust_address" class="form-control" rows="3"
                                    placeholder="Alamat lengkap..."></textarea>
                            </div>
                            <div class="form-group">
                                <label for="cust_contract">No Kontrak</label>
                                <input type="text" name="contract_no" id="cust_contract" class="form-control"
                                    placeholder="Contoh: KONTRAK-2026-001">
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width: 100%; justify-content: center;">Simpan Customer</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Bulk Import Customer (CSV)</div>
                            <a href="{{ route('master_data.sample_csv', 'customer') }}" class="btn btn-outline btn-icon-sm"
                                title="Download Sample CSV"><i class="fa-solid fa-download"></i> Sample</a>
                        </div>
                        <form action="{{ route('master_data.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="type" value="customer">
                            <input type="hidden" name="tab" value="customer">
                            <div class="form-group">
                                <label>File CSV (Format: name,phone,address,contract_no)</label>
                                <input type="file" name="file" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline" style="width: 100%; justify-content: center;"><i
                                    class="fa-solid fa-file-import"></i> Upload & Import</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script>
        const tabWhBtn = document.getElementById('tabWhBtn');
        const tabTechBtn = document.getElementById('tabTechBtn');
        const tabAccBtn = document.getElementById('tabAccBtn');
        const tabSimBtn = document.getElementById('tabSimBtn');
        const tabDeviceModelBtn = document.getElementById('tabDeviceModelBtn');
        const tabCustomerBtn = document.getElementById('tabCustomerBtn');

        const panelWh = document.getElementById('panelWh');
        const panelTech = document.getElementById('panelTech');
        const panelAcc = document.getElementById('panelAcc');
        const panelSim = document.getElementById('panelSim');
        const panelDeviceModel = document.getElementById('panelDeviceModel');
        const panelCustomer = document.getElementById('panelCustomer');

        // Map tab names to their button and panel
        const tabMap = {
            warehouse: { btn: tabWhBtn, panel: panelWh, color: 'var(--accent-blue)' },
            technician: { btn: tabTechBtn, panel: panelTech, color: 'var(--accent-indigo)' },
            accessory: { btn: tabAccBtn, panel: panelAcc, color: 'var(--accent-amber)' },
            simcard: { btn: tabSimBtn, panel: panelSim, color: 'var(--accent-rose)' },
            device_model: { btn: tabDeviceModelBtn, panel: panelDeviceModel, color: 'var(--accent-emerald)' },
            customer: { btn: tabCustomerBtn, panel: panelCustomer, color: 'var(--accent-indigo)' },
        };

        function activateTab(name) {
            const entry = tabMap[name] || tabMap['warehouse'];
            // Reset all
            Object.values(tabMap).forEach(({ btn, panel }) => {
                btn.style.borderBottomColor = 'transparent';
                btn.style.color = 'var(--text-secondary)';
                panel.style.display = 'none';
            });
            // Activate selected
            entry.btn.style.borderBottomColor = entry.color;
            entry.btn.style.color = 'var(--text-primary)';
            entry.panel.style.display = 'block';
        }

        // Wire up click listeners
        Object.entries(tabMap).forEach(([name, { btn }]) => {
            btn.addEventListener('click', () => activateTab(name));
        });

        // On page load: read ?tab from URL (set after redirect) and open correct tab
        (function () {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab') || 'warehouse';
            activateTab(tab);
        })();

        // Helper functions to populate Edit fields
        window.editWarehouse = function (code, name, type) {
            document.getElementById('wh_code').value = code;
            document.getElementById('wh_code').readOnly = true;
            document.getElementById('wh_name').value = name;
            document.getElementById('wh_type').value = type;
            document.getElementById('whFormTitle').innerText = 'Ubah Gudang (' + code + ')';
        }

        window.editTechnician = function (code, name, area) {
            document.getElementById('tech_code').value = code;
            document.getElementById('tech_code').readOnly = true;
            document.getElementById('tech_name').value = name;
            document.getElementById('tech_area').value = area || '';
            document.getElementById('techFormTitle').innerText = 'Ubah Teknisi (' + code + ')';
        }

        window.editAccessory = function (code, name, qty) {
            document.getElementById('acc_code').value = code;
            document.getElementById('acc_code').readOnly = true;
            document.getElementById('acc_name').value = name;
            document.getElementById('acc_qty').value = qty;
            document.getElementById('accFormTitle').innerText = 'Ubah Aksesoris (' + code + ')';
        }

        window.editSimcard = function (id, msisdn, provider, category, status) {
            document.getElementById('sim_id').value = id;
            document.getElementById('sim_msisdn').value = msisdn;
            document.getElementById('sim_provider').value = provider;
            document.getElementById('sim_category').value = category;
            document.getElementById('sim_status').value = status;
            document.getElementById('simFormTitle').innerText = 'Ubah GSM SIM Card';
        }

        window.editDeviceModel = function (id, brand, type, model) {
            document.getElementById('dm_brand').value = brand;
            document.getElementById('dm_type').value = type;
            document.getElementById('dm_model').value = model;
            document.getElementById('dm_model').readOnly = true;
            document.getElementById('dmFormTitle').innerText = 'Ubah Model Perangkat';
        }

        window.editCustomer = function (id, name, phone, address, contract) {
            document.getElementById('cust_id').value = id;
            document.getElementById('cust_name').value = name;
            document.getElementById('cust_phone').value = phone;
            document.getElementById('cust_address').value = address;
            document.getElementById('cust_contract').value = contract;
            document.getElementById('custFormTitle').innerText = 'Ubah Customer';
        }
    </script>
@endsection