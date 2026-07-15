@php
    $themeMode = \App\Models\AppSetting::getValue('theme_mode', 'dark');
    $appLogo = \App\Models\AppSetting::getValue('app_logo');
    $appFavicon = \App\Models\AppSetting::getValue('app_favicon');
    $authUser = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="id" class="{{ $themeMode === 'light' ? 'light-theme' : 'dark-theme' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Gudang Kerja | WMS DLMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @if($appFavicon)
        <link rel="icon" href="{{ asset($appFavicon) }}">
    @endif
    <style>
        .warehouse-selector-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            padding: 20px;
        }

        .selector-container {
            width: 100%;
            max-width: 680px;
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .selector-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .selector-header .logo-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-indigo));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 8px 30px rgba(79, 70, 229, 0.3);
        }

        .selector-header .logo-icon i {
            font-size: 28px;
            color: #fff;
        }

        .selector-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .selector-header p {
            font-size: 15px;
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .warehouse-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .warehouse-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 24px 20px;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .warehouse-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-indigo));
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .warehouse-card:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .warehouse-card:hover::before {
            opacity: 1;
        }

        .warehouse-card.selected {
            border-color: var(--accent-blue);
            background: rgba(59, 130, 246, 0.08);
        }

        .warehouse-card.selected::before {
            opacity: 1;
        }

        .warehouse-card .wh-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            font-size: 18px;
        }

        .warehouse-card .wh-icon.pusat {
            background: rgba(59, 130, 246, 0.15);
            color: var(--accent-blue);
        }

        .warehouse-card .wh-icon.regional {
            background: rgba(168, 85, 247, 0.15);
            color: var(--accent-indigo);
        }

        .warehouse-card .wh-icon.cabang {
            background: rgba(34, 197, 94, 0.15);
            color: var(--accent-green);
        }

        .warehouse-card .wh-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .warehouse-card .wh-code {
            font-size: 12px;
            color: var(--text-muted);
            font-family: monospace;
            margin-bottom: 8px;
        }

        .warehouse-card .wh-type {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 3px 8px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-secondary);
        }

        .warehouse-card .wh-stats {
            margin-top: 12px;
            font-size: 11px;
            color: var(--text-muted);
        }

        .warehouse-card .check-mark {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--accent-blue);
            color: #fff;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .warehouse-card.selected .check-mark {
            display: flex;
        }

        .selector-actions {
            text-align: center;
        }

        .selector-actions .btn-primary {
            padding: 14px 48px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
        }

        .selector-actions .btn-primary:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .selector-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-muted);
        }
    </style>
</head>

<body>
    <div class="warehouse-selector-page">
        <div class="selector-container">
            <div class="selector-header">
                <div class="logo-icon">
                    @if($appLogo)
                        <img src="{{ asset($appLogo) }}" alt="Logo" style="width: 36px; height: 36px; object-fit: contain;">
                    @else
                        <i class="fa-solid fa-warehouse"></i>
                    @endif
                </div>
                <h1>Pilih Gudang Kerja</h1>
                <p>
                    @if($authUser?->isSuperAdmin())
                        Pilih gudang kerja kamu terlebih dahulu, warehouse pusat dan global hanya bisa view only, sesuaikan dengan kebutuhan mu
                    @elseif($authUser?->hasRole(\App\Models\User::ROLE_ADMIN))
                        Pilih gudang kerja Anda. Anda dapat memilih Global, East, West, atau gudang cabang sesuai yang didaftarkan.
                    @else
                        Pilih gudang tempat Anda beroperasi. Hubungi Super Admin jika gudang Anda belum ditetapkan di akun.
                    @endif
                </p>
            </div>

            <form action="{{ route('set.warehouse') }}" method="POST" id="warehouseForm">
                @csrf
                <input type="hidden" name="warehouse_code" id="selectedWarehouseCode" value="">
                <input type="hidden" name="warehouse_name" id="selectedWarehouseName" value="">

                @if($authUser?->canSelectWarehouse())
                    <style>
                        .selector-container { max-width: 1000px !important; }
                        
                        /* Top Container for Pusat */
                        .pusat-container {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 32px;
                            margin-bottom: 32px;
                        }
                        
                        .global-container {
                            display: grid;
                            grid-template-columns: 1fr;
                            gap: 32px;
                            margin-bottom: 32px;
                        }
                        @media (max-width: 768px) {
                            .pusat-container { grid-template-columns: 1fr; gap: 24px; }
                        }
                        
                        /* Huge Card for Pusat */
                        .pusat-huge-card {
                            background: var(--bg-secondary);
                            border: 2px solid var(--border-color);
                            border-radius: 20px;
                            padding: 40px 24px;
                            text-align: center;
                            cursor: pointer;
                            transition: all 0.3s;
                            box-shadow: 0 8px 30px rgba(0,0,0,0.04);
                            position: relative;
                        }
                        .pusat-huge-card:hover {
                            transform: translateY(-5px);
                            border-color: var(--accent-blue);
                            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.12);
                        }
                        .pusat-huge-card.selected {
                            border-color: var(--accent-blue);
                            background: rgba(59, 130, 246, 0.05);
                        }
                        .pusat-huge-card .huge-icon {
                            font-size: 64px;
                            margin-bottom: 20px;
                        }
                        .pusat-huge-card.east .huge-icon { color: var(--accent-blue); }
                        .pusat-huge-card.west .huge-icon { color: var(--accent-indigo); }
                        .pusat-huge-card.global .huge-icon { color: #10b981; }
                        
                        .pusat-huge-card .wh-name {
                            font-size: 24px;
                            font-weight: 700;
                            color: var(--text-primary);
                            margin-bottom: 8px;
                        }
                        .pusat-huge-card .wh-code {
                            font-size: 14px;
                            color: var(--text-muted);
                            font-family: monospace;
                            margin-bottom: 12px;
                        }
                        .pusat-huge-card .wh-stats {
                            font-size: 13px;
                            color: var(--text-secondary);
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            background: rgba(0,0,0,0.04);
                            padding: 6px 12px;
                            border-radius: 20px;
                        }
                        
                        .pusat-huge-card .check-mark {
                            position: absolute;
                            top: 20px;
                            right: 20px;
                            width: 32px;
                            height: 32px;
                            border-radius: 50%;
                            background: var(--accent-blue);
                            color: #fff;
                            display: none;
                            align-items: center;
                            justify-content: center;
                            font-size: 14px;
                        }
                        .pusat-huge-card.selected .check-mark { display: flex; }

                        /* Divider */
                        .cabang-divider {
                            display: flex;
                            align-items: center;
                            text-align: center;
                            color: var(--text-muted);
                            font-size: 14px;
                            font-weight: 600;
                            text-transform: uppercase;
                            letter-spacing: 2px;
                            margin-bottom: 24px;
                        }
                        .cabang-divider::before, .cabang-divider::after {
                            content: '';
                            flex: 1;
                            border-bottom: 1px solid var(--border-color);
                        }
                        .cabang-divider::before { margin-right: 24px; }
                        .cabang-divider::after { margin-left: 24px; }

                        /* Bottom Container for Cabang (4 Columns) */
                        .cabang-container {
                            display: grid;
                            grid-template-columns: repeat(4, 1fr);
                            gap: 20px;
                            margin-bottom: 32px;
                        }
                        @media (max-width: 992px) {
                            .cabang-container { grid-template-columns: repeat(2, 1fr); }
                        }
                        @media (max-width: 480px) {
                            .cabang-container { grid-template-columns: 1fr; }
                        }
                    </style>
                    
                    <!-- MENU WAREHOUSE GLOBAL -->
                    <div class="global-container">
                        @php
                            $totalDevices = \App\Models\Device::where('status', 'IN_STOCK')->count();
                        @endphp
                        <div class="warehouse-card pusat-huge-card global" 
                             data-code="__global__" 
                             data-name="Warehouse Global"
                             onclick="selectWarehouse(this, '__global__', 'Warehouse Global')">
                            <div class="check-mark"><i class="fa-solid fa-check"></i></div>
                            <div class="huge-icon"><i class="fa-solid fa-globe"></i></div>
                            <div class="wh-name">Warehouse Global</div>
                            <div class="wh-stats"><i class="fa-solid fa-box"></i> {{ $totalDevices }} device in-stock (All Area)</div>
                        </div>
                    </div>

                    <!-- 2 MENU UTAMA REGIONAL (East Area / West Area) -->
                    @if(isset($allRegions) && $allRegions->count() > 0)
                    <div class="pusat-container">
                        @foreach($allRegions as $regionName)
                            @php
                                $regionCodes = \App\Models\Warehouse::where('region', $regionName)
                                    ->whereRaw('LOWER(type) != ?', ['pusat'])
                                    ->pluck('code')->toArray();
                                $deviceCount = \App\Models\Device::whereIn('warehouse_code', $regionCodes)->where('status', 'IN_STOCK')->count();
                                $areaClass = strtoupper($regionName) === 'EAST' ? 'east' : 'west';
                                $iconClass = strtoupper($regionName) === 'EAST' ? 'fa-building-circle-check' : 'fa-building-flag';
                                $regionCode = '__region_' . strtoupper($regionName) . '__';
                                $regionLabel = ucfirst(strtolower($regionName)) . ' Area';
                                $branchCount = count($regionCodes);
                            @endphp
                            <div class="warehouse-card pusat-huge-card {{ $areaClass }}" 
                                 data-code="{{ $regionCode }}" 
                                 data-name="{{ $regionLabel }}"
                                 onclick="selectWarehouse(this, '{{ $regionCode }}', '{{ $regionLabel }}')">
                                <div class="check-mark"><i class="fa-solid fa-check"></i></div>
                                <div class="huge-icon"><i class="fa-solid {{ $iconClass }}"></i></div>
                                <div class="wh-name">{{ $regionLabel }}</div>
                                <div class="wh-code" style="font-size: 12px; margin-bottom: 4px;">{{ $branchCount }} gudang cabang</div>
                                <div class="wh-stats"><i class="fa-solid fa-box"></i> {{ $deviceCount }} device in-stock (All {{ strtoupper($regionName) }})</div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    <!-- SUB MENU CABANG — dikelompokkan per REGION -->
                    <div class="cabang-divider">Pilih Gudang Cabang</div>

                    @php
                        $allCabang = $warehouses->filter(function($w) {
                            return strtolower($w->type) !== 'pusat' && $w->code !== '__global__';
                        })->sortBy('name');

                        $cabangEast = $allCabang->filter(fn($w) => strtoupper($w->region ?? '') === 'EAST');
                        $cabangWest = $allCabang->filter(fn($w) => strtoupper($w->region ?? '') === 'WEST');
                        $cabangNoRegion = $allCabang->filter(fn($w) => empty($w->region));
                    @endphp

                    @foreach([['EAST', $cabangEast, 'fa-building-circle-check', 'var(--accent-blue)'], ['WEST', $cabangWest, 'fa-building-flag', 'var(--accent-indigo)']] as [$regionLabel, $regionWarehouses, $regionIcon, $regionColor])
                        @if($regionWarehouses->isNotEmpty())
                            <div style="margin-bottom: 28px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid {{ $regionColor }}22;">
                                    <i class="fa-solid {{ $regionIcon }}" style="color: {{ $regionColor }}; font-size: 18px;"></i>
                                    <span style="font-size: 14px; font-weight: 700; color: {{ $regionColor }}; text-transform: uppercase; letter-spacing: 1px;">
                                        Region {{ $regionLabel }}
                                    </span>
                                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">
                                        ({{ $regionWarehouses->count() }} gudang)
                                    </span>
                                </div>
                                <div class="cabang-container">
                                    @foreach($regionWarehouses as $wh)
                                        @php
                                            $deviceCount = \App\Models\Device::where('warehouse_code', $wh->code)->where('status', 'IN_STOCK')->count();
                                        @endphp
                                        <div class="warehouse-card"
                                             data-code="{{ $wh->code }}"
                                             data-name="{{ $wh->name }}"
                                             onclick="selectWarehouse(this, '{{ $wh->code }}', '{{ $wh->name }}')">
                                            <div class="check-mark"><i class="fa-solid fa-check"></i></div>
                                            <div class="wh-icon cabang" style="color: {{ $regionColor }};">
                                                <i class="fa-solid fa-store"></i>
                                            </div>
                                            <div class="wh-name">{{ $wh->name }}</div>
                                            <div class="wh-code">{{ $wh->code }}</div>
                                            <span class="wh-type" style="background: {{ $regionColor }}22; color: {{ $regionColor }}; border-color: {{ $regionColor }}44;">{{ $regionLabel }}</span>
                                            <div class="wh-stats">
                                                <i class="fa-solid fa-box"></i> {{ $deviceCount }} device in-stock
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    @if($cabangNoRegion->isNotEmpty())
                        <div style="margin-bottom: 28px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color);">
                                <i class="fa-solid fa-warehouse" style="color: var(--text-muted); font-size: 18px;"></i>
                                <span style="font-size: 14px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">
                                    Tanpa Region
                                </span>
                                <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">
                                    ({{ $cabangNoRegion->count() }} gudang)
                                </span>
                            </div>
                            <div class="cabang-container">
                                @foreach($cabangNoRegion as $wh)
                                    @php
                                        $deviceCount = \App\Models\Device::where('warehouse_code', $wh->code)->where('status', 'IN_STOCK')->count();
                                    @endphp
                                    <div class="warehouse-card"
                                         data-code="{{ $wh->code }}"
                                         data-name="{{ $wh->name }}"
                                         onclick="selectWarehouse(this, '{{ $wh->code }}', '{{ $wh->name }}')">
                                        <div class="check-mark"><i class="fa-solid fa-check"></i></div>
                                        <div class="wh-icon cabang">
                                            <i class="fa-solid fa-store"></i>
                                        </div>
                                        <div class="wh-name">{{ $wh->name }}</div>
                                        <div class="wh-code">{{ $wh->code }}</div>
                                        <span class="wh-type">CABANG</span>
                                        <div class="wh-stats">
                                            <i class="fa-solid fa-box"></i> {{ $deviceCount }} device in-stock
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif


                @else
                    <!-- Fallback untuk selain Super Admin (misal ada bypass limitasi suatu saat) -->
                    <div class="warehouse-grid">
                        @foreach($warehouses as $wh)
                            @php
                                $typeClass = strtolower($wh->type) === 'pusat' ? 'pusat' : (strtolower($wh->type) === 'regional' ? 'regional' : 'cabang');
                                $typeIcon = strtolower($wh->type) === 'pusat' ? 'fa-building' : (strtolower($wh->type) === 'regional' ? 'fa-city' : 'fa-store');
                                $deviceCount = \App\Models\Device::where('warehouse_code', $wh->code)->where('status', 'IN_STOCK')->count();
                            @endphp
                            <div class="warehouse-card" 
                                 data-code="{{ $wh->code }}" 
                                 data-name="{{ $wh->name }}"
                                 onclick="selectWarehouse(this, '{{ $wh->code }}', '{{ $wh->name }}')">
                                <div class="check-mark"><i class="fa-solid fa-check"></i></div>
                                <div class="wh-icon {{ $typeClass }}">
                                    <i class="fa-solid {{ $typeIcon }}"></i>
                                </div>
                                <div class="wh-name">{{ $wh->name }}</div>
                                <div class="wh-code">{{ $wh->code }}</div>
                                <span class="wh-type">{{ $wh->type }}</span>
                                <div class="wh-stats">
                                    <i class="fa-solid fa-box"></i> {{ $deviceCount }} device in-stock
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="selector-actions">
                    <button type="submit" class="btn btn-primary" id="btnConfirm" disabled>
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        Masuk ke Gudang
                    </button>
                </div>
            </form>

            <div class="selector-footer">
                <i class="fa-solid fa-circle-info"></i>
                @if($authUser?->isSuperAdmin())
                    Super Admin dapat beralih gudang kapan saja lewat navbar.
                @elseif($authUser?->hasRole(\App\Models\User::ROLE_ADMIN))
                    Admin dapat beralih gudang kapan saja lewat navbar.
                @elseif($authUser?->isWarehouseBound())
                    Gudang Anda ditetapkan oleh administrator dan tidak dapat diubah.
                @else
                    Setelah gudang ditetapkan di akun Anda, pemilihan manual tidak diperlukan lagi.
                @endif
            </div>
        </div>
    </div>

    <script>
        function toggleArea(areaId) {
            const content = document.getElementById('area-' + areaId);
            const icon = document.getElementById('icon-' + areaId);
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                // Opsional: Tutup area lain jika ingin gaya accordion murni
                // document.querySelectorAll('.area-content').forEach(el => el.classList.remove('active'));
                // document.querySelectorAll('.area-header i.fa-chevron-up').forEach(el => {
                //     el.classList.remove('fa-chevron-up');
                //     el.classList.add('fa-chevron-down');
                // });
                
                content.classList.add('active');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        function selectWarehouse(el, code, name) {
            // Remove previous selection
            document.querySelectorAll('.warehouse-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Set selection
            el.classList.add('selected');
            document.getElementById('selectedWarehouseCode').value = code;
            document.getElementById('selectedWarehouseName').value = name;
            document.getElementById('btnConfirm').disabled = false;
        }
    </script>
</body>
</html>
