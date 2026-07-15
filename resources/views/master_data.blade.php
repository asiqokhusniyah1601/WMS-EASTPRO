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
                <i class="fa-solid fa-warehouse" style="color: var(--accent-blue);"></i> Gudang (Warehouses)
            </button>
            <button class="btn btn-outline" id="tabDeviceModelBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-microchip" style="color: var(--accent-emerald);"></i> Device Models
            </button>
            <button class="btn btn-outline" id="tabAccBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-toolbox" style="color: var(--accent-amber);"></i> Aksesoris (Accessories)
            </button>
            <button class="btn btn-outline" id="tabSimBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-sim-card" style="color: var(--accent-rose);"></i> GSM SIM Cards
            </button>
            <button class="btn btn-outline" id="tabTechBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-user-tie" style="color: var(--accent-indigo);"></i> Teknisi (Technicians)
            </button>
            <button class="btn btn-outline" id="tabCustomerBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-users" style="color: var(--accent-indigo);"></i> Pelanggan (Customer)
            </button>
            <button class="btn btn-outline" id="tabRackBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-layer-group" style="color: var(--accent-amber);"></i> Rak Penyimpanan
            </button>
            <button class="btn btn-outline" id="tabBarcodeBtn"
                style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
                <i class="fa-solid fa-barcode" style="color: var(--accent-emerald);"></i> Barcode Generator
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
                        <table class="table" id="table-warehouse">
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
                                        <td>
                                            <span class="badge badge-info">{{ $wh['type'] ?? 'CABANG' }}</span>
                                            @if(!empty($wh['region']))
                                                <span class="badge badge-secondary" style="background-color: var(--accent-indigo) !important; color: white;">{{ $wh['region'] }}</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm" title="Pengaturan Batas Min Stok"
                                                onclick="document.getElementById('threshold-{{ $wh['code'] }}').style.display = document.getElementById('threshold-{{ $wh['code'] }}').style.display === 'none' ? 'table-row' : 'none';"><i
                                                    class="fa-solid fa-gear"></i></button>
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editWarehouse('{{ $wh['code'] }}', '{{ $wh['name'] }}', '{{ $wh['type'] ?? 'CABANG' }}', '{{ $wh['region'] ?? '' }}')"><i
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
                                    <tr id="threshold-{{ $wh['code'] }}" style="display: none;">
                                        <td colspan="4" style="background: rgba(0,0,0,0.02); border-left: 3px solid var(--accent-blue); padding: 16px;">
                                            <div style="font-weight: 600; margin-bottom: 12px; color: var(--text-secondary);">Batas Minimum Stok - {{ $wh['name'] }}</div>
                                            
                                            <!-- Existing Thresholds -->
                                            @if(isset($thresholds[$wh['code']]) && count($thresholds[$wh['code']]) > 0)
                                            <table class="table" style="background: white; margin-bottom: 16px;">
                                                <thead>
                                                    <tr>
                                                        <th>Tipe</th>
                                                        <th>Item</th>
                                                        <th>Batas Min</th>
                                                        <th style="text-align: right;">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($thresholds[$wh['code']] as $th)
                                                    <tr>
                                                        <td><span class="badge badge-secondary">{{ $th->item_type }}</span></td>
                                                        <td>{{ $th->item_identifier }}</td>
                                                        <td><strong>{{ $th->min_stock_level }}</strong></td>
                                                        <td style="text-align: right;">
                                                            <form action="{{ route('master_data.warehouse_threshold.delete', $th->id) }}" method="POST" style="display:inline;">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger btn-icon-sm" onclick="return confirm('Hapus batas ini?')"><i class="fa-solid fa-times"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            @endif

                                            <!-- Add New Threshold Form -->
                                            <form action="{{ route('master_data.warehouse_threshold.store') }}" method="POST" style="display: flex; gap: 8px; align-items: flex-end;">
                                                @csrf
                                                <input type="hidden" name="warehouse_code" value="{{ $wh['code'] }}">
                                                <div class="form-group" style="margin-bottom: 0; flex: 1;">
                                                    <label style="font-size: 11px;">Tipe Item</label>
                                                    <select name="item_type" class="form-control" style="padding: 6px; height: auto;" onchange="updateThresholdIdentifier('{{ $wh['code'] }}', this.value)">
                                                        <option value="DEVICE">DEVICE</option>
                                                        <option value="ACCESSORY">ACCESSORY</option>
                                                        <option value="SIMCARD">SIMCARD</option>
                                                    </select>
                                                </div>
                                                <div class="form-group" style="margin-bottom: 0; flex: 2;">
                                                    <label style="font-size: 11px;">Item Identifier</label>
                                                    <select name="item_identifier" id="ident-{{ $wh['code'] }}" class="form-control" style="padding: 6px; height: auto;">
                                                        <!-- Option values will be filled by JS based on item_type -->
                                                        @foreach($deviceModels as $dm)
                                                            <option value="{{ $dm['model'] }}">{{ $dm['model'] }} ({{ $dm['brand'] }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group" style="margin-bottom: 0; flex: 1;">
                                                    <label style="font-size: 11px;">Batas Min</label>
                                                    <input type="number" name="min_stock_level" class="form-control" style="padding: 6px; height: auto;" min="0" value="0" required>
                                                </div>
                                                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; height: 34px;">Tambah</button>
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
                        <form action="{{ route('master_data.warehouse.store') }}" method="POST" id="warehouseForm">
                            @csrf
                            <input type="hidden" name="_method" value="POST" id="wh_method">
                            <input type="hidden" name="tab" value="warehouse">
                            <div class="form-group">
                                <label for="wh_code">Kode Gudang</label>
                                <input type="text" name="code" id="wh_code" class="form-control"
                                    placeholder="Contoh: WH-PUSAT" required>
                                <small class="text-muted" id="wh_code_help" style="display: none;">Mengubah kode akan mensinkronkan semua data terkait (devices, transaksi, dll).</small>
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
                            <div class="form-group">
                                <label for="wh_region">Regional</label>
                                <select name="region" id="wh_region" class="form-control">
                                    <option value="">-- Tanpa Regional --</option>
                                    <option value="EAST">EAST</option>
                                    <option value="WEST">WEST</option>
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
                                <label>File CSV (Format: code,name,type,region)</label>
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
                        <table class="table" id="table-technician">
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
                                            <button type="button" class="btn btn-outline btn-icon-sm" title="Pengaturan Batas Pegang"
                                                onclick="document.getElementById('tech-limit-{{ $tech['code'] }}').style.display = document.getElementById('tech-limit-{{ $tech['code'] }}').style.display === 'none' ? 'table-row' : 'none';"><i
                                                    class="fa-solid fa-gear"></i></button>
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
                                    <tr id="tech-limit-{{ $tech['code'] }}" style="display: none;">
                                        <td colspan="4" style="background: rgba(0,0,0,0.02); border-left: 3px solid var(--accent-indigo); padding: 16px;">
                                            <div style="font-weight: 600; margin-bottom: 12px; color: var(--text-secondary);">Batas Minimal Perangkat - {{ $tech['name'] }}</div>
                                            
                                            <!-- Existing Limits -->
                                            @if(isset($technicianLimits[$tech['code']]) && count($technicianLimits[$tech['code']]) > 0)
                                            <table class="table" style="background: white; margin-bottom: 16px;">
                                                <thead>
                                                    <tr>
                                                        <th>Kategori</th>
                                                        <th>Batas Minimal</th>
                                                        <th style="text-align: right;">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($technicianLimits[$tech['code']] as $th)
                                                    <tr>
                                                        <td><span class="badge badge-indigo">{{ $th->item_identifier }}</span></td>
                                                        <td><strong>{{ $th->min_stock_level }}</strong> unit</td>
                                                        <td style="text-align: right;">
                                                            <form action="{{ route('master_data.technician_limit.delete', $th->id) }}" method="POST" style="display:inline;">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger btn-icon-sm" onclick="return confirm('Hapus batas ini?')"><i class="fa-solid fa-times"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            @endif

                                            <!-- Add New Limit Form -->
                                            <form action="{{ route('master_data.technician_limit.store') }}" method="POST" style="display: flex; gap: 8px; align-items: flex-end;">
                                                @csrf
                                                <input type="hidden" name="technician_code" value="{{ $tech['code'] }}">
                                                <div class="form-group" style="margin-bottom: 0; flex: 2;">
                                                    <label style="font-size: 11px;">Kategori Perangkat</label>
                                                    <select name="category" class="form-control" style="padding: 6px; height: auto;">
                                                        <option value="GPS Tracker">GPS Tracker</option>
                                                        <option value="Dashcam">Dashcam</option>
                                                        <option value="eSeal">eSeal</option>
                                                        <option value="MDVR">MDVR</option>
                                                        <option value="SIM Card">SIM Card</option>
                                                        <option value="Lainnya">Lainnya</option>
                                                    </select>
                                                </div>
                                                <div class="form-group" style="margin-bottom: 0; flex: 1;">
                                                    <label style="font-size: 11px;">Batas Minimal</label>
                                                    <input type="number" name="min_required" class="form-control" style="padding: 6px; height: auto;" min="0" value="0" required>
                                                </div>
                                                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; height: 34px;">Simpan Batas</button>
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
                        <div class="card-title">Daftar Aksesoris &amp; Stok</div>
                        @if($activeWarehouseCode)
                            <span class="badge badge-info" style="font-size: 12px;">
                                <i class="fa-solid fa-warehouse"></i>
                                {{ $activeWarehouseName ?? $activeWarehouseCode }}
                            </span>
                        @else
                            <span class="badge" style="background: rgba(99,102,241,0.12); color: var(--accent-indigo); border: 1px solid rgba(99,102,241,0.25); font-size: 12px;">
                                <i class="fa-solid fa-globe"></i> Semua Gudang (Global)
                            </span>
                        @endif
                    </div>
                    <div class="table-wrapper">
                        <table class="table" id="table-accessory">
                            <thead>
                                <tr>
                                    <th>Kode Aksesoris</th>
                                    <th>Nama Barang</th>
                                    <th>Satuan</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accessories as $acc)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--accent-amber);">{{ $acc['code'] }}</td>
                                        <td>{{ $acc['name'] }}</td>
                                        <td>
                                            <span class="badge badge-info" style="background: rgba(99,102,241,0.1); color: var(--accent-indigo); border: 1px solid rgba(99,102,241,0.2);">{{ $acc['unit'] ?? 'pcs' }}</span>
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editAccessory('{{ $acc['code'] }}', '{{ $acc['name'] }}', {{ $acc['qty'] }}, '{{ $acc['unit'] ?? 'pcs' }}')"><i
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
                                <label for="acc_unit">Satuan</label>
                                <select name="unit" id="acc_unit" class="form-control">
                                    <option value="pcs">pcs</option>
                                    <option value="unit">unit</option>
                                    <option value="pack">pack</option>
                                    <option value="box">box</option>
                                    <option value="set">set</option>
                                    <option value="m">m</option>
                                    <option value="roll">roll</option>
                                    <option value="g">g</option>
                                    <option value="pair">pair</option>
                                    <option value="lembar">lembar</option>
                                </select>
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
                        @if($activeWarehouseCode)
                            <span class="badge badge-info" style="font-size: 12px;">
                                <i class="fa-solid fa-warehouse"></i>
                                {{ $activeWarehouseName ?? $activeWarehouseCode }}
                            </span>
                        @else
                            <span class="badge" style="background: rgba(99,102,241,0.12); color: var(--accent-indigo); border: 1px solid rgba(99,102,241,0.25); font-size: 12px;">
                                <i class="fa-solid fa-globe"></i> Semua Gudang (Global)
                            </span>
                        @endif
                    </div>
                    <div class="table-wrapper">
                        <table class="table" id="table-simcard">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Provider</th>
                                    <th>Kategori</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($simcards as $idx => $sim)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td style="font-weight: 600; color: var(--accent-rose);">{{ $sim['kode'] }}</td>
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
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editSimcard({{ $sim['id'] }}, '{{ $sim['kode'] }}', '{{ $sim['provider'] }}', '{{ $sim['category'] ?? '' }}')"><i
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
                                <label for="sim_kode">Kode</label>
                                <input type="text" name="kode" id="sim_kode" class="form-control"
                                    placeholder="Contoh: TELKOMSEL_HALO" required>
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
                        <table class="table" id="table-devicemodel">
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
                        <table class="table" id="table-customer">
                            <thead>
                                <tr>
                                    <th>Nama Lengkap</th>
                                    <th>Telepon</th>
                                    <th>Nama PIC</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customers as $cust)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--accent-indigo);">{{ $cust['name'] }}</td>
                                        <td>{{ $cust['phone'] ?? '-' }}</td>
                                        <td>{{ $cust['pic_name'] ?? '-' }}</td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm"
                                                onclick="editCustomer({{ $cust['id'] }}, '{{ $cust['name'] }}', '{{ $cust['phone'] }}', '{{ $cust['address'] }}', '{{ $cust['pic_name'] }}')"><i
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
                                <label for="cust_contract">Nama PIC</label>
                                <input type="text" name="pic_name" id="cust_contract" class="form-control"
                                    placeholder="Contoh: Budi">
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
                                <label>File CSV (Format: name,phone,address,pic_name)</label>
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
        <!-- TAB 7: RACK STORAGE PANEL -->
        <!-- ========================================== -->
        <div id="panelRack" class="tab-panel" style="display: none;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                <!-- Racks & Devices List -->
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <!-- Racks Table -->
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="card-title">Daftar Rak Penyimpanan</div>
                            <a href="{{ route('master_data.rack.export') }}" class="btn btn-outline btn-icon-sm" title="Download Excel Data Rak & Device">
                                <i class="fa-solid fa-download"></i> Download Excel
                            </a>
                        </div>
                        <div class="table-wrapper">
                            <table class="table" id="table-rack">
                                <thead>
                                    <tr>
                                        <th>Barcode Rak</th>
                                        <th>Kode Rak</th>
                                        <th>Kode Baris</th>
                                        <th>Gudang</th>
                                        <th style="text-align: right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($racks as $rack)
                                        <tr>
                                            <td style="font-weight: 600; color: var(--accent-amber);">{{ $rack->barcode }}</td>
                                            <td>{{ $rack->rack_code }}</td>
                                            <td>{{ $rack->row_code }}</td>
                                            <td><span class="badge badge-secondary" style="font-size: 11px;">{{ $rack->warehouse_code }}</span></td>
                                            <td style="text-align: right;">
                                                <form action="{{ route('master_data.rack.delete', $rack->id) }}" method="POST" style="display:inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-icon-sm" onclick="return confirm('Hapus rak ini?')"><i class="fa-solid fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Devices Table -->
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="card-title">Data Device di Rak</div>
                            <div style="display: flex; gap: 8px;">
                                <select id="filterRack" class="form-control" style="width: 250px; height: 34px; padding: 4px 8px;">
                                    <option value="">-- Semua Rak --</option>
                                    @foreach($racks as $rack)
                                        <option value="{{ $rack->barcode }}">{{ $rack->barcode }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary" style="height: 34px; padding: 0 12px;" onclick="applyRackFilter()">
                                    <i class="fa-solid fa-filter"></i> Filter
                                </button>
                            </div>
                        </div>
                        <div class="table-wrapper">
                            <table class="table" id="table-rack-devices">
                                <thead>
                                    <tr>
                                        <th>Serial Number</th>
                                        <th>Model</th>
                                        <th>Barcode Rak</th>
                                        <th>Status</th>
                                        @if(!$activeWarehouseCode)
                                            <th>Gudang</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($devicesInRack as $dev)
                                        <tr class="rack-device-row" data-rack="{{ $dev->rack_barcode }}">
                                            <td style="font-weight: 600; color: var(--accent-blue);">{{ $dev->serial_number }}</td>
                                            <td>{{ $dev->model }}</td>
                                            <td><span class="badge badge-info">{{ $dev->rack_barcode }}</span></td>
                                            <td><span class="badge badge-success">{{ $dev->status }}</span></td>
                                            @if(!$activeWarehouseCode)
                                                <td><span class="badge badge-secondary" style="font-size: 11px;">{{ $dev->warehouse_code }}</span></td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Forms -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Tambah Rak Penyimpanan</div>
                        </div>
                        <form action="{{ route('master_data.rack.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="tab" value="rack">
                            <div class="form-group">
                                <label for="rack_wh">Gudang</label>
                                <x-warehouse-select name="warehouse_code" id="rack_wh" :warehouses="$warehouses" :readonly="true" />
                            </div>
                            <div class="form-group">
                                <label for="rack_barcode">Barcode Rak Lengkap</label>
                                <input type="text" name="barcode" id="rack_barcode" class="form-control" placeholder="Contoh: WS-RAK-01-ROW-01 (Wajib ada WS)" required>
                            </div>
                            <div class="form-group">
                                <label for="rack_code">Kode Rak (Opsional)</label>
                                <input type="text" name="rack_code" id="rack_code" class="form-control" placeholder="Contoh: RAK-01">
                            </div>
                            <div class="form-group">
                                <label for="row_code">Kode Baris/Row (Opsional)</label>
                                <input type="text" name="row_code" id="row_code" class="form-control" placeholder="Contoh: ROW-01">
                            </div>
                            <div class="form-group">
                                <label for="rack_desc">Deskripsi</label>
                                <input type="text" name="description" id="rack_desc" class="form-control" placeholder="Opsional...">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Simpan Rak</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 8: BARCODE GENERATOR -->
        <!-- ========================================== -->
        <div id="panelBarcode" class="tab-panel" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-barcode"></i> Generate Barcode Lokasi / Barang
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="barcode_strings">Masukkan String (Satu per baris)</label>
                        <textarea id="barcode_strings" class="form-control" rows="5" placeholder="Contoh:&#10;RAK-01-ROW-01&#10;RAK-01-ROW-02&#10;ACC-12345"></textarea>
                        <small style="color: var(--text-muted); margin-top: 4px; display: block;">Masukkan teks yang ingin diubah menjadi barcode. Gunakan enter untuk memisahkan teks jika ingin men-generate banyak barcode sekaligus.</small>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="generateBarcodes()">
                        <i class="fa-solid fa-gears"></i> Generate Barcode
                    </button>
                </div>
            </div>

            <div class="card" id="generatorResultCard" style="display: none; margin-top: 24px;">
                <div class="card-header" style="justify-content: space-between;">
                    <div class="card-title">
                        <i class="fa-solid fa-image"></i> Hasil Generate Barcode (<span id="barcodeCount">0</span>)
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn btn-success" onclick="downloadAllBarcodes()">
                            <i class="fa-solid fa-download"></i> Download Semua (.jpg)
                        </button>
                    </div>
                </div>
                <div class="card-body" style="background: var(--bg-secondary);">
                    <div id="barcodePreviewArea" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px;">
                        <!-- Previews will be appended here -->
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
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
        const tabRackBtn = document.getElementById('tabRackBtn');
        const panelRack = document.getElementById('panelRack');
        const tabBarcodeBtn = document.getElementById('tabBarcodeBtn');
        const panelBarcode = document.getElementById('panelBarcode');

        // Map tab names to their button and panel
        const tabMap = {
            warehouse: { btn: tabWhBtn, panel: panelWh, color: 'var(--accent-blue)' },
            technician: { btn: tabTechBtn, panel: panelTech, color: 'var(--accent-indigo)' },
            accessory: { btn: tabAccBtn, panel: panelAcc, color: 'var(--accent-amber)' },
            simcard: { btn: tabSimBtn, panel: panelSim, color: 'var(--accent-rose)' },
            device_model: { btn: tabDeviceModelBtn, panel: panelDeviceModel, color: 'var(--accent-emerald)' },
            customer: { btn: tabCustomerBtn, panel: panelCustomer, color: 'var(--accent-indigo)' },
            rack: { btn: tabRackBtn, panel: panelRack, color: 'var(--accent-amber)' },
            barcode: { btn: tabBarcodeBtn, panel: panelBarcode, color: 'var(--accent-emerald)' },
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
        window.editWarehouse = function (code, name, type, region) {
            document.getElementById('wh_code').value = code;
            document.getElementById('wh_code').readOnly = false; // Boleh diedit
            document.getElementById('wh_name').value = name;
            document.getElementById('wh_type').value = type;
            document.getElementById('wh_region').value = region || '';
            document.getElementById('whFormTitle').innerText = 'Ubah Gudang (' + code + ')';
            document.getElementById('wh_code_help').style.display = 'block';

            // Ubah form menjadi PUT untuk update route
            document.getElementById('warehouseForm').action = '/master-data/warehouse/' + encodeURIComponent(code);
            document.getElementById('wh_method').value = 'PUT';
        }

        window.editTechnician = function (code, name, area) {
            document.getElementById('tech_code').value = code;
            document.getElementById('tech_code').readOnly = true;
            document.getElementById('tech_name').value = name;
            document.getElementById('tech_area').value = area || '';
            document.getElementById('techFormTitle').innerText = 'Ubah Teknisi (' + code + ')';
        }

        window.editAccessory = function (code, name, qty, unit) {
            document.getElementById('accFormTitle').innerText = 'Edit Aksesoris (Qty dikunci)';
            document.getElementById('acc_code').value = code;
            document.getElementById('acc_code').readOnly = true;
            document.getElementById('acc_name').value = name;
            
            let unitSelect = document.getElementById('acc_unit');
            if (unitSelect && unit) {
                unitSelect.value = unit;
            }

            window.scrollTo({ top: document.getElementById('accFormTitle').offsetTop - 100, behavior: 'smooth' });
        };

        window.editSimcard = function (id, kode, provider, category) {
            document.getElementById('sim_id').value = id;
            document.getElementById('sim_kode').value = kode;
            document.getElementById('sim_provider').value = provider;
            document.getElementById('sim_category').value = category;
            document.getElementById('simFormTitle').innerText = 'Ubah GSM SIM Card Master';
        }

        window.editDeviceModel = function (id, brand, type, model) {
            document.getElementById('dm_brand').value = brand;
            document.getElementById('dm_type').value = type;
            document.getElementById('dm_model').value = model;
            document.getElementById('dm_model').value = model;
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

        const deviceModelsList = @json(array_column($deviceModels, 'model'));
        const accessoriesList = @json(array_column($accessories, 'code'));
        const simcardProviders = @json(array_unique(array_column($simcards, 'provider')));

        window.updateThresholdIdentifier = function(whCode, type) {
            const select = document.getElementById('ident-' + whCode);
            if (!select) return;
            select.innerHTML = '';
            let options = [];
            if (type === 'DEVICE') {
                options = deviceModelsList;
            } else if (type === 'ACCESSORY') {
                options = accessoriesList;
            } else if (type === 'SIMCARD') {
                options = simcardProviders;
            }
            options.forEach(opt => {
                if(opt) {
                    select.innerHTML += `<option value="${opt}">${opt}</option>`;
                }
            });
        }
        // ==========================================
        // CLIENT-SIDE PAGINATION LOGIC
        // ==========================================
        // Inject CSS for pagination controls
        const style = document.createElement('style');
        style.innerHTML = `
            .pagination-controls {
                display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
                padding: 12px 16px; border-top: 1px solid var(--border-color);
                background: var(--bg-secondary); border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;
            }
            .pagination-controls .page-size { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-secondary); }
            .pagination-controls .page-nav { display: flex; align-items: center; gap: 4px; }
            .pagination-controls .page-nav button { padding: 4px 10px; border: 1px solid var(--border-color); background: var(--bg-color); border-radius: 6px; cursor: pointer; color: var(--text-primary); font-size: 13px; }
            .pagination-controls .page-nav button:disabled { opacity: 0.5; cursor: not-allowed; }
            .pagination-controls .page-nav span { font-size: 13px; color: var(--text-secondary); margin: 0 8px; }
        `;
        document.head.appendChild(style);

        function initPagination(tableId, rowsPerPageOptions = [10, 20, 50, 100]) {
            const table = document.getElementById(tableId);
            if (!table) return;
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            let rows = [];
            const trs = Array.from(tbody.children);
            for (let i = 0; i < trs.length; i++) {
                const tr = trs[i];
                if (tr.id && (tr.id.startsWith('threshold-') || tr.id.startsWith('tech-limit-'))) {
                    if (rows.length > 0) rows[rows.length - 1].detailRow = tr;
                    continue;
                }
                if (tr.children.length === 1 && tr.innerText.trim() === 'Belum ada transaksi terekam.') return;
                rows.push({ mainRow: tr, detailRow: null });
            }

            if (rows.length === 0) return;

            let currentPage = 1;
            let rowsPerPage = rowsPerPageOptions[0];
            const totalRows = rows.length;

            const controls = document.createElement('div');
            controls.className = 'pagination-controls';

            const sizeSelector = document.createElement('div');
            sizeSelector.className = 'page-size';
            const span1 = document.createElement('span'); span1.innerText = 'Tampilkan';
            const select = document.createElement('select');
            select.className = 'form-control';
            select.style.width = '70px'; select.style.padding = '4px 8px'; select.style.height = 'auto';
            rowsPerPageOptions.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt; option.text = opt;
                select.appendChild(option);
            });
            select.addEventListener('change', (e) => {
                rowsPerPage = parseInt(e.target.value);
                currentPage = 1;
                render();
            });
            const span2 = document.createElement('span'); span2.innerText = 'baris';
            sizeSelector.appendChild(span1); sizeSelector.appendChild(select); sizeSelector.appendChild(span2);

            const nav = document.createElement('div');
            nav.className = 'page-nav';
            const btnPrev = document.createElement('button'); btnPrev.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
            const btnNext = document.createElement('button'); btnNext.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
            const pageInfo = document.createElement('span');

            btnPrev.addEventListener('click', () => { if (currentPage > 1) { currentPage--; render(); } });
            btnNext.addEventListener('click', () => { if (currentPage * rowsPerPage < totalRows) { currentPage++; render(); } });

            nav.appendChild(btnPrev); nav.appendChild(pageInfo); nav.appendChild(btnNext);
            controls.appendChild(sizeSelector); controls.appendChild(nav);

            const wrapper = table.closest('.table-wrapper');
            if (wrapper && wrapper.parentNode) {
                wrapper.parentNode.insertBefore(controls, wrapper.nextSibling);
            }

            function render() {
                const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
                if (currentPage > totalPages) currentPage = totalPages;

                const start = (currentPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;

                rows.forEach((r, index) => {
                    if (index >= start && index < end) {
                        r.mainRow.style.display = '';
                    } else {
                        r.mainRow.style.display = 'none';
                        if (r.detailRow) r.detailRow.style.display = 'none'; // Sembunyikan detail saat pindah page
                    }
                });

                pageInfo.innerText = `Halaman ${currentPage} dari ${totalPages}`;
                btnPrev.disabled = currentPage === 1;
                btnNext.disabled = currentPage === totalPages;
            }
            render();
        }

        document.addEventListener('DOMContentLoaded', () => {
            initPagination('table-warehouse');
            initPagination('table-technician');
            initPagination('table-accessory');
            initPagination('table-simcard');
            initPagination('table-devicemodel');
            initPagination('table-customer');
            initPagination('table-rack');
            initPagination('table-rack-devices');
        });
        function applyRackFilter() {
            const filterValue = document.getElementById('filterRack').value;
            const rows = document.querySelectorAll('.rack-device-row');
            
            rows.forEach(row => {
                const rackBarcode = row.getAttribute('data-rack');
                if (filterValue === '' || rackBarcode === filterValue) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // ==========================================
        // BARCODE GENERATOR LOGIC
        // ==========================================
        let generatedFiles = [];

        function generateBarcodes() {
            const textInput = document.getElementById('barcode_strings').value;
            const lines = textInput.split('\n').map(line => line.trim()).filter(line => line.length > 0);
            
            if (lines.length === 0) {
                alert('Silakan masukkan minimal satu string.');
                return;
            }

            const previewArea = document.getElementById('barcodePreviewArea');
            previewArea.innerHTML = ''; // clear previous
            generatedFiles = [];

            lines.forEach((text, index) => {
                // Create a wrapper
                const wrap = document.createElement('div');
                wrap.style.background = '#fff';
                wrap.style.padding = '16px';
                wrap.style.borderRadius = '8px';
                wrap.style.border = '1px solid #ccc';
                wrap.style.textAlign = 'center';
                wrap.style.display = 'flex';
                wrap.style.flexDirection = 'column';
                wrap.style.alignItems = 'center';
                wrap.style.gap = '12px';

                // Create canvas for JsBarcode
                const canvas = document.createElement('canvas');
                canvas.id = 'barcode_canvas_' + index;
                wrap.appendChild(canvas);

                // Create download button
                const btn = document.createElement('button');
                btn.className = 'btn btn-sm btn-outline';
                btn.innerHTML = '<i class="fa-solid fa-download"></i> Download';
                btn.onclick = () => downloadSingleBarcode(canvas, text);
                wrap.appendChild(btn);

                previewArea.appendChild(wrap);

                // Generate barcode
                try {
                    JsBarcode(canvas, text, {
                        format: "CODE128",
                        lineColor: "#000",
                        width: 2,
                        height: 60,
                        displayValue: true,
                        margin: 10,
                        background: "#ffffff"
                    });
                    
                    // Save for "Download All"
                    generatedFiles.push({ canvas: canvas, name: text });
                } catch (e) {
                    console.error("Gagal generate barcode untuk:", text);
                    wrap.innerHTML = '<span style="color:red">Error: ' + text + '</span>';
                }
            });

            document.getElementById('barcodeCount').innerText = generatedFiles.length;
            document.getElementById('generatorResultCard').style.display = 'block';
        }

        function downloadSingleBarcode(canvas, text) {
            // Create white background image (JPEG doesn't support transparency)
            const ctx = canvas.getContext("2d");
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            
            // Fill white
            ctx.fillStyle = "#FFFFFF";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            // Put original image back over the white background
            JsBarcode(canvas, text, { format: "CODE128", width: 2, height: 60, displayValue: true, margin: 10, background: "#ffffff" });

            const url = canvas.toDataURL("image/jpeg", 1.0);
            const a = document.createElement('a');
            a.href = url;
            a.download = `barcode_${text.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.jpg`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function downloadAllBarcodes() {
            if (generatedFiles.length === 0) return;
            
            // Add slight delay between downloads so browser doesn't block them
            let delay = 0;
            generatedFiles.forEach(file => {
                setTimeout(() => {
                    downloadSingleBarcode(file.canvas, file.name);
                }, delay);
                delay += 300; // 300ms delay
            });
        }
    </script>
@endsection