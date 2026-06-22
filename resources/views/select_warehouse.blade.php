@php
    $warehouses = \App\Models\Warehouse::all();
    $themeMode = \App\Models\AppSetting::getValue('theme_mode', 'dark');
    $appLogo = \App\Models\AppSetting::getValue('app_logo');
    $appFavicon = \App\Models\AppSetting::getValue('app_favicon');
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
                <p>Pilih gudang tempat Anda beroperasi hari ini. Semua transaksi akan otomatis tercatat ke gudang yang dipilih.</p>
            </div>

            <form action="{{ route('set.warehouse') }}" method="POST" id="warehouseForm">
                @csrf
                <input type="hidden" name="warehouse_code" id="selectedWarehouseCode" value="">
                <input type="hidden" name="warehouse_name" id="selectedWarehouseName" value="">

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

                <div class="selector-actions">
                    <button type="submit" class="btn btn-primary" id="btnConfirm" disabled>
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        Masuk ke Gudang
                    </button>
                </div>
            </form>

            <div class="selector-footer">
                <i class="fa-solid fa-circle-info"></i>
                Anda bisa mengganti gudang kapan saja lewat navbar di dalam aplikasi.
            </div>
        </div>
    </div>

    <script>
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
