@php
    $themeMode = \App\Models\AppSetting::getValue('theme_mode', 'dark');
    $appFavicon = \App\Models\AppSetting::getValue('app_favicon');
    $appLogo = \App\Models\AppSetting::getValue('app_logo');

    // Alert stok minimum (StockAlertThreshold) untuk ikon lonceng & badge menu.
    $alertScope = session('active_warehouse_code');
    $stockAlerts = (new \App\Services\DashboardInsightService())->getStockAlerts($alertScope);
    $alertCount = count($stockAlerts);
@endphp
<!DOCTYPE html>
<html lang="id" class="{{ $themeMode === 'light' ? 'light-theme' : 'dark-theme' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Apply UI density early to avoid layout flash -->
    <script>
        (function () {
            var d = localStorage.getItem('uiDensity') || 'comfortable';
            document.documentElement.setAttribute('data-density', d);
        })();
    </script>
    <title>@yield('title', 'WMS - Device Lifecycle Management')</title>
    <!-- Modern Premium Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if($appFavicon)
        <link rel="icon" href="{{ asset($appFavicon) }}">
    @endif
    <style>
        .notif-wrapper { position: relative; }
        .notif-bell {
            position: relative; background: none; border: 1px solid var(--border-color); color: var(--text-secondary);
            width: 38px; height: 38px; border-radius: 10px; cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;
        }
        .notif-bell:hover { color: var(--text-primary); border-color: var(--accent-blue); }
        .notif-badge {
            position: absolute; top: -6px; right: -6px; min-width: 18px; height: 18px; padding: 0 5px;
            background: var(--accent-red, #ef4444); color: #fff; font-size: 10px; font-weight: 700;
            border-radius: 9px; display: flex; align-items: center; justify-content: center; line-height: 1;
        }
        .notif-dropdown {
            display: none; position: absolute; top: 48px; right: 0; width: 360px; max-width: 90vw;
            background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.30); z-index: 1000; overflow: hidden;
        }
        .notif-wrapper.open .notif-dropdown { display: block; animation: notifFade 0.15s ease-out; }
        @keyframes notifFade { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        .notif-head {
            display: flex; justify-content: space-between; align-items: center; padding: 12px 16px;
            border-bottom: 1px solid var(--border-color); font-size: 13px; font-weight: 600; color: var(--text-primary);
        }
        .notif-head a { font-size: 12px; color: var(--accent-blue); text-decoration: none; font-weight: 500; }
        .notif-list { max-height: 360px; overflow-y: auto; }
        .notif-item {
            display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; text-decoration: none;
            border-bottom: 1px solid var(--border-color); border-left: 3px solid transparent; transition: background 0.15s ease;
        }
        .notif-item:hover { background: var(--bg-primary); }
        .notif-item.critical { border-left-color: var(--accent-red, #ef4444); }
        .notif-item.warning { border-left-color: var(--accent-amber, #f59e0b); }
        .notif-item i { font-size: 14px; margin-top: 2px; color: var(--text-muted); }
        .notif-item.critical i { color: var(--accent-red, #ef4444); }
        .notif-item.warning i { color: var(--accent-amber, #f59e0b); }
        .notif-msg { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
        .notif-msg strong { color: var(--text-primary); }
        .notif-empty { padding: 28px 16px; text-align: center; font-size: 13px; color: var(--text-muted); }
        .notif-empty i { color: var(--accent-emerald, #10b981); margin-right: 6px; }

        /* Density toggle button (Comfortable / Compact) */
        .density-toggle {
            background: none; border: 1px solid var(--border-color); color: var(--text-secondary);
            width: 38px; height: 38px; border-radius: 10px; cursor: pointer; font-size: 15px;
            display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;
        }
        .density-toggle:hover { color: var(--text-primary); border-color: var(--accent-blue); }
        html[data-density="compact"] .density-toggle { color: var(--accent-blue); border-color: var(--accent-blue); }

        /* ===== Compact density overrides (global) ===== */
        html[data-density="compact"] .app-content { padding: 18px; }
        html[data-density="compact"] .card { padding: 16px; margin-bottom: 16px; }
        html[data-density="compact"] .card-header { margin-bottom: 12px; padding-bottom: 10px; }
        html[data-density="compact"] .card-title { font-size: 16px; }
        html[data-density="compact"] .stat-card { padding: 15px; }
        html[data-density="compact"] .stat-value { font-size: 22px; }
        html[data-density="compact"] .stats-grid { gap: 14px; }
        html[data-density="compact"] .table th,
        html[data-density="compact"] .table td { padding: 8px 12px; }
        html[data-density="compact"] .dash-split { gap: 16px; margin-top: 16px; }
    </style>
    @yield('styles')
</head>

<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar" id="sidebar">
            <script>
                if (localStorage.getItem('sidebarState') === 'collapsed') {
                    document.getElementById('sidebar').classList.add('collapsed');
                }
            </script>
            <div class="sidebar-header">
                @if($appLogo)
                    <img src="{{ asset($appLogo) }}" alt="App Logo" style="height: 32px; width: 32px; object-fit: contain; border-radius: 6px;">
                @else
                    <i class="fa-solid fa-boxes-stacked brand-icon"></i>
                @endif
                <div>
                    <h1 class="brand-name">WMS EASTPRO</h1>
                    <span class="brand-sub">PT EasyGo Indonesia</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <!-- MONITORING -->
                    <li class="nav-section"><span>Monitoring</span></li>
                    <li class="{{ Route::currentRouteName() === 'dashboard' ? 'active' : '' }}">
                        <a href="{{ route('dashboard') }}" title="Dashboard">
                            <i class="fa-solid fa-chart-pie"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                 <!--   <li class="{{ Route::currentRouteName() === 'alerts' ? 'active' : '' }}">
                        <a href="{{ route('alerts') }}" title="Alert Center">
                            <i class="fa-solid fa-bell"></i>
                            <span>Alert Center</span>
                            @if(($alertCount ?? 0) > 0)
                                <span class="badge badge-danger nav-badge">{{ $alertCount }}</span>
                            @endif
                        </a>
                    </li> -->

                    <!-- OPERASIONAL GUDANG (alur barang masuk -> pindah -> keluar -> kembali) -->
                    <li class="nav-section"><span>Operasional Gudang</span></li>
                    <li class="{{ Route::currentRouteName() === 'receiving' ? 'active' : '' }}">
                        <a href="{{ route('receiving') }}" title="Penerimaan barang masuk (scan)">
                            <i class="fa-solid fa-file-import"></i>
                            <span>Barang Masuk</span>
                            <span class="badge badge-success nav-badge">Scan</span>
                        </a>
                    </li>
                    <li class="{{ Route::currentRouteName() === 'transfer' ? 'active' : '' }}">
                        <a href="{{ route('transfer') }}" title="Transfer antar gudang">
                            <i class="fa-solid fa-truck-ramp-box"></i>
                            <span>Transfer Gudang</span>
                        </a>
                    </li>
                    <li class="{{ Route::currentRouteName() === 'issue' ? 'active' : '' }}">
                        <a href="{{ route('issue') }}" title="Serahkan perangkat ke teknisi / customer">
                            <i class="fa-solid fa-user-gear"></i>
                            <span>Serah Terima</span>
                        </a>
                    </li>
                    <li class="{{ Route::currentRouteName() === 'return' ? 'active' : '' }}">
                        <a href="{{ route('return') }}" title="Pengembalian perangkat ke gudang">
                            <i class="fa-solid fa-boxes-packing"></i>
                            <span>Return Perangkat</span>
                        </a>
                    </li>

                    <!-- KONTROL & KUALITAS -->
                    <li class="nav-section"><span>Kontrol & Kualitas</span></li>
                    @php
                        $qcQueueCount = \App\Models\Device::where('status', 'PENDING_QC')
                            ->when(session('active_warehouse_code'), fn ($q) => $q->where('warehouse_code', session('active_warehouse_code')))
                            ->count()
                            + \App\Models\Device::whereIn('status', ['RETURNED', 'UNDER_QC'])->count();
                    @endphp
                    <li class="{{ in_array(Route::currentRouteName(), ['quality.control', 'qc.incoming', 'inspection']) ? 'active' : '' }}">
                        <a href="{{ route('quality.control') }}" title="Quality Control: barang masuk, return, laporan">
                            <i class="fa-solid fa-clipboard-check"></i>
                            <span>Quality Control</span>
                            @if($qcQueueCount > 0)
                                <span class="badge badge-warning nav-badge">{{ $qcQueueCount }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="{{ Route::currentRouteName() === 'stock.opname' ? 'active' : '' }}">
                        <a href="{{ route('stock.opname') }}" title="Stock Opname / Koreksi Stok">
                            <i class="fa-solid fa-scale-balanced"></i>
                            <span>Stock Opname</span>
                        </a>
                    </li>

                    <!-- DATA & LAPORAN -->
                    <li class="nav-section"><span>Data & Laporan</span></li>
                    <li class="{{ Route::currentRouteName() === 'search' ? 'active' : '' }}">
                        <a href="{{ route('search') }}" title="Pencarian & jejak audit perangkat">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span>Search & Audit</span>
                        </a>
                    </li>
                    <li class="{{ Route::currentRouteName() === 'reports' ? 'active' : '' }}">
                        <a href="{{ route('reports') }}" title="Laporan & analitik">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Laporan & Analitik</span>
                        </a>
                    </li>

                    @if(auth()->user()?->isSuperAdmin())
                    <!-- ADMINISTRASI (khusus Super Admin) -->
                    <li class="nav-section"><span>Administrasi</span></li>
                    <li class="{{ Route::currentRouteName() === 'master_data' ? 'active' : '' }}">
                        <a href="{{ route('master_data') }}" title="Master Data">
                            <i class="fa-solid fa-database"></i>
                            <span>Master Data</span>
                        </a>
                    </li>
                    <li class="{{ Route::currentRouteName() === 'users.index' ? 'active' : '' }}">
                        <a href="{{ route('users.index') }}" title="Manajemen Pengguna & Role">
                            <i class="fa-solid fa-users-gear"></i>
                            <span>Manajemen Pengguna</span>
                        </a>
                    </li>
                    <li class="{{ Route::currentRouteName() === 'settings' ? 'active' : '' }}">
                        <a href="{{ route('settings') }}" title="Pengaturan aplikasi">
                            <i class="fa-solid fa-gear"></i>
                            <span>Pengaturan</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>

            <div class="sidebar-footer">
                @php($authUser = auth()->user())
                <div class="user-profile" style="display: flex; align-items: center; gap: 10px;">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($authUser?->name ?? 'User') }}&background=4f46e5&color=fff"
                        alt="User Avatar" class="user-avatar">
                    <div class="user-info" style="flex: 1; min-width: 0;">
                        <span class="user-name">{{ $authUser?->name ?? 'Pengguna' }}</span>
                        <span class="user-role">{{ $authUser?->roleLabel() ?? '' }}</span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn-icon-sm btn-logout" title="Keluar / Logout"
                            style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; font-size: 15px;"
                            onclick="return confirm('Yakin ingin keluar?')">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <!-- Header -->
            <header class="app-header">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="header-search">
                    <form action="{{ route('search') }}" method="GET">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" name="q" placeholder="Cari Serial Number, IMEI, atau Plat Kendaraan..."
                            value="{{ request('q') }}">
                    </form>
                </div>
                <div class="header-actions">
                    <button type="button" class="cmdk-hint-btn" id="cmdkTrigger" title="Buka Command Palette">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Perintah Cepat</span>
                        <kbd>⌘K</kbd>
                    </button>
                    <button type="button" class="density-toggle" id="densityToggle" title="Kepadatan tampilan">
                        <i class="fa-solid fa-table-cells-large"></i>
                    </button>
                    @if(session('active_warehouse_code'))
                    <a href="{{ route('select.warehouse') }}" class="warehouse-indicator" title="Klik untuk ganti gudang" style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(99,102,241,0.10)); border: 1px solid rgba(59,130,246,0.25); border-radius: 8px; padding: 6px 14px; text-decoration: none; transition: all 0.2s ease; cursor: pointer;">
                        <i class="fa-solid fa-warehouse" style="color: var(--accent-blue); font-size: 13px;"></i>
                        <div style="display: flex; flex-direction: column; line-height: 1.2;">
                            <span style="font-size: 11px; font-weight: 600; color: var(--text-primary);">{{ session('active_warehouse_name') }}</span>
                            <span style="font-size: 9px; color: var(--text-muted); font-family: monospace;">{{ session('active_warehouse_code') }} · {{ session('active_warehouse_type') }}</span>
                        </div>
                        <i class="fa-solid fa-repeat" style="color: var(--text-muted); font-size: 10px; margin-left: 4px;"></i>
                    </a>
                    @endif
                    <!-- Alert Notification Bell -->
                    <div class="notif-wrapper" id="notifWrapper">
                        <button type="button" class="notif-bell" id="notifBell" title="Alert Stok Minimum" aria-label="Alert">
                            <i class="fa-solid fa-bell"></i>
                            @if($alertCount > 0)
                                <span class="notif-badge">{{ $alertCount > 99 ? '99+' : $alertCount }}</span>
                            @endif
                        </button>
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-head">
                                <span><i class="fa-solid fa-triangle-exclamation"></i> Alert Stok Minimum</span>
                                <a href="{{ route('alerts') }}">Lihat semua</a>
                            </div>
                            <div class="notif-list">
                                @forelse($stockAlerts as $a)
                                    <x-alert-item :alert="$a" variant="bell" />
                                @empty
                                    <div class="notif-empty">
                                        <i class="fa-solid fa-circle-check"></i> Semua stok berada di atas batas minimum.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                <!--     <div class="scanner-status">
                        <span class="status-indicator online"></span>
                        <span class="status-text">HID Scanner Ready</span>
                    </div>-->
                </div>
            </header>

            <!-- Page Content -->
            <main class="app-content">
                @if (session('success'))
                    <div class="alert-box alert-success animate-fade-in">
                        <div class="alert-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="alert-message">{{ session('success') }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert-box alert-danger animate-fade-in">
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

                @yield('content')
            </main>
        </div>
    </div>

    <!-- COMMAND PALETTE (Ctrl/Cmd + K) -->
    <div class="cmdk-overlay" id="cmdkOverlay" role="dialog" aria-modal="true">
        <div class="cmdk-box" role="document">
            <div class="cmdk-input-row">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="cmdkInput" placeholder="Ketik perintah, menu, atau Serial Number..." autocomplete="off" spellcheck="false">
                <kbd>ESC</kbd>
            </div>
            <div class="cmdk-results" id="cmdkResults"></div>
        </div>
    </div>

    @if(false)
        <!-- VIRTUAL BARCODE SCANNER EMULATOR OVERLAY -->
        <div class="scanner-emulator" id="scannerEmulator">
            <div class="emulator-header" id="emulatorHeader">
                <div class="title-group">
                    <i class="fa-solid fa-barcode header-icon"></i>
                    <h4>Virtual Barcode HID Scanner</h4>
                </div>
                <div class="header-controls">
                    <button id="toggleEmulator" class="btn-icon-sm"><i class="fa-solid fa-chevron-up"></i></button>
                </div>
            </div>
            <div class="emulator-body" id="emulatorBody">
                <p class="emulator-help">Simulasikan scan barcode dengan mengklik tombol Serial Number di bawah atau ketik
                    kustom SN.</p>

                <div class="form-group">
                    <label>Pilih Target Input Halaman</label>
                    <select id="emulatorTarget">
                        <option value=".scan-target-input">Main Scan Input (Auto-focus)</option>
                        <option value="#manual_sn_input">Custom Selector</option>
                    </select>
                </div>

                <div class="emulator-presets">
                    <span class="preset-title">Daftar Barcode Dummy:</span>
                    <div class="preset-grid">
                        <button class="preset-btn" data-sn="GPS-982173812" data-type="GPS Tracker">
                            <span>GPS-982173812</span>
                            <small class="tag duplicate">Duplicate DB</small>
                        </button>
                        <button class="preset-btn" data-sn="GPS-NEW-99001" data-type="GPS Tracker">
                            <span>GPS-NEW-99001</span>
                            <small class="tag new">New Stock</small>
                        </button>
                        <button class="preset-btn" data-sn="GPS-NEW-99002" data-type="GPS Tracker">
                            <span>GPS-NEW-99002</span>
                            <small class="tag new">New Stock</small>
                        </button>
                        <button class="preset-btn" data-sn="MDVR-NEW-88022" data-type="MDVR">
                            <span>MDVR-NEW-88022</span>
                            <small class="tag new">MDVR</small>
                        </button>
                        <button class="preset-btn" data-sn="CAM-NEW-77051" data-type="Dashcam">
                            <span>CAM-NEW-77051</span>
                            <small class="tag new">Dashcam</small>
                        </button>
                        <button class="preset-btn" data-sn="GPS-982173812" data-type="GPS Tracker">
                            <span>Double Click Test</span>
                        </button>
                    </div>
                </div>

                <div class="emulator-custom">
                    <div class="input-group-inline">
                        <input type="text" id="emulatorCustomSn" placeholder="Ketik Serial Number Kustom...">
                        <button id="btnSimulateScan" class="btn btn-primary">Scan Enter</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Web Audio API for Barcode Scanner sound effects -->
    <script>
        // Sound player
        const beepSound = {
            ctx: null,
            init() {
                if (!this.ctx) {
                    this.ctx = new (window.AudioContext || window.webkitAudioContext)();
                }
            },
            play(type = 'success') {
                try {
                    this.init();
                    if (this.ctx.state === 'suspended') {
                        this.ctx.resume();
                    }

                    const osc = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();

                    osc.connect(gain);
                    gain.connect(this.ctx.destination);

                    if (type === 'success') {
                        // High pitch quick double beep or single beep
                        osc.frequency.setValueAtTime(1000, this.ctx.currentTime);
                        gain.gain.setValueAtTime(0.1, this.ctx.currentTime);
                        osc.start();
                        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.1);
                        osc.stop(this.ctx.currentTime + 0.15);
                    } else if (type === 'error') {
                        // Low warning beep
                        osc.type = 'sawtooth';
                        osc.frequency.setValueAtTime(150, this.ctx.currentTime);
                        gain.gain.setValueAtTime(0.15, this.ctx.currentTime);
                        osc.start();
                        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.4);
                        osc.stop(this.ctx.currentTime + 0.45);
                    }
                } catch (e) {
                    console.error("Audio Web API error:", e);
                }
            }
        };

        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                if (sidebar.classList.contains('collapsed')) {
                    localStorage.setItem('sidebarState', 'collapsed');
                } else {
                    localStorage.setItem('sidebarState', 'expanded');
                }
            });
        }

        // Density toggle (Comfortable / Compact) — persisted in localStorage
        const densityToggle = document.getElementById('densityToggle');
        function applyDensity(d) {
            document.documentElement.setAttribute('data-density', d);
            localStorage.setItem('uiDensity', d);
            if (densityToggle) {
                const compact = d === 'compact';
                densityToggle.querySelector('i').className = compact ? 'fa-solid fa-table-cells' : 'fa-solid fa-table-cells-large';
                densityToggle.title = compact ? 'Mode Compact — klik untuk Comfortable' : 'Mode Comfortable — klik untuk Compact';
            }
        }
        if (densityToggle) {
            densityToggle.addEventListener('click', () => {
                const cur = document.documentElement.getAttribute('data-density') || 'comfortable';
                applyDensity(cur === 'compact' ? 'comfortable' : 'compact');
            });
            applyDensity(localStorage.getItem('uiDensity') || 'comfortable');
        }

        // Notification bell dropdown toggle
        const notifWrapper = document.getElementById('notifWrapper');
        const notifBell = document.getElementById('notifBell');
        if (notifBell && notifWrapper) {
            notifBell.addEventListener('click', (e) => {
                e.stopPropagation();
                notifWrapper.classList.toggle('open');
            });
            document.addEventListener('click', (e) => {
                if (!notifWrapper.contains(e.target)) {
                    notifWrapper.classList.remove('open');
                }
            });
        }

        // Scanner Emulator UI control
        const scannerEmulator = document.getElementById('scannerEmulator');
        const toggleEmulator = document.getElementById('toggleEmulator');
        const emulatorBody = document.getElementById('emulatorBody');

        if (toggleEmulator) {
            toggleEmulator.addEventListener('click', () => {
                scannerEmulator.classList.toggle('collapsed');
                const isCollapsed = scannerEmulator.classList.contains('collapsed');
                toggleEmulator.innerHTML = isCollapsed ? '<i class="fa-solid fa-chevron-up"></i>' : '<i class="fa-solid fa-chevron-down"></i>';
            });
        }

        // Emulator Simulator function
        function triggerScanInput(sn, type = 'GPS Tracker') {
            const selector = document.getElementById('emulatorTarget').value;
            const targetInput = document.querySelector(selector);

            if (!targetInput) {
                alert('Elemen input scan aktif tidak ditemukan di halaman ini. Kunjungi menu Web Receiving / Transfer / Issue.');
                return;
            }

            // Fill value
            targetInput.value = sn;

            // Set type if page contains type selector
            const typeSelector = document.getElementById('scan_type');
            if (typeSelector) {
                typeSelector.value = type;
            }

            // Flash effect on input
            targetInput.classList.add('flash-highlight');
            setTimeout(() => targetInput.classList.remove('flash-highlight'), 300);

            // Emit Enter Key event
            const event = new KeyboardEvent('keydown', {
                key: 'Enter',
                code: 'Enter',
                keyCode: 13,
                which: 13,
                bubbles: true,
                cancelable: true
            });
            targetInput.dispatchEvent(event);
        }

        // Preset button clicks
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const sn = btn.getAttribute('data-sn');
                const type = btn.getAttribute('data-type');
                triggerScanInput(sn, type);
            });
        });

        // Custom simulate
        const btnSimulateScan = document.getElementById('btnSimulateScan');
        const emulatorCustomSn = document.getElementById('emulatorCustomSn');

        if (btnSimulateScan && emulatorCustomSn) {
            btnSimulateScan.addEventListener('click', () => {
                const sn = emulatorCustomSn.value.trim();
                if (sn) {
                    triggerScanInput(sn);
                    emulatorCustomSn.value = '';
                }
            });
            emulatorCustomSn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    btnSimulateScan.click();
                }
            });
        }

        // Expose beep globally
        window.playBeep = function (type) {
            beepSound.play(type);
        };
    </script>

    <!-- COMMAND PALETTE LOGIC -->
    <script>
    (function () {
        const navCommands = [
            { title: 'Dashboard', sub: 'Ringkasan & monitoring', icon: 'fa-chart-pie', url: @json(route('dashboard')) },
            { title: 'Alert Center', sub: 'Peringatan stok minimum', icon: 'fa-bell', url: @json(route('alerts')) },
            { title: 'Penerimaan (Receiving)', sub: 'Scan barang masuk', icon: 'fa-file-import', url: @json(route('receiving')) },
            { title: 'Transfer Gudang', sub: 'Pindah antar gudang', icon: 'fa-truck-ramp-box', url: @json(route('transfer')) },
            { title: 'Serah Terima', sub: 'Serahkan ke teknisi/customer', icon: 'fa-user-gear', url: @json(route('issue')) },
            { title: 'Return Perangkat', sub: 'Pengembalian ke gudang', icon: 'fa-boxes-packing', url: @json(route('return')) },
            { title: 'Quality Control', sub: 'QC barang masuk, return & laporan', icon: 'fa-clipboard-check', url: @json(route('quality.control')) },
            { title: 'QC Return / Inspeksi', sub: 'Inspeksi perangkat return', icon: 'fa-magnifying-glass-chart', url: @json(route('quality.control', ['tab' => 'return'])) },
            { title: 'Laporan QC', sub: 'Reject rate, lead time, throughput', icon: 'fa-chart-column', url: @json(route('quality.control', ['tab' => 'report'])) },
            { title: 'Stock Opname', sub: 'Koreksi stok', icon: 'fa-scale-balanced', url: @json(route('stock.opname')) },
            { title: 'Search & Audit', sub: 'Pencarian & jejak audit', icon: 'fa-magnifying-glass', url: @json(route('search')) },
            { title: 'Laporan & Analitik', sub: 'Laporan & ekspor', icon: 'fa-chart-line', url: @json(route('reports')) },
            @if(session('active_warehouse_code'))
            { title: 'Ganti Gudang Aktif', sub: 'Pilih gudang lain', icon: 'fa-warehouse', url: @json(route('select.warehouse')) },
            @endif
            @if(auth()->user()?->isSuperAdmin())
            { title: 'Master Data', sub: 'Kelola data master', icon: 'fa-database', url: @json(route('master_data')) },
            { title: 'Manajemen Pengguna', sub: 'Pengguna & role', icon: 'fa-users-gear', url: @json(route('users.index')) },
            { title: 'Pengaturan', sub: 'Tema & branding', icon: 'fa-gear', url: @json(route('settings')) },
            @endif
        ];
        const searchBase = @json(route('search'));

        const overlay = document.getElementById('cmdkOverlay');
        const input = document.getElementById('cmdkInput');
        const results = document.getElementById('cmdkResults');
        const trigger = document.getElementById('cmdkTrigger');
        if (!overlay || !input || !results) return;

        let active = 0;
        let current = [];

        function buildList(q) {
            const term = (q || '').trim().toLowerCase();
            const matched = navCommands.filter(c =>
                !term || c.title.toLowerCase().includes(term) || (c.sub && c.sub.toLowerCase().includes(term))
            );
            current = [];
            let html = '';

            if (matched.length) {
                html += '<div class="cmdk-group-label">Navigasi</div>';
                matched.forEach(c => {
                    const idx = current.length;
                    current.push({ url: c.url });
                    html += `<div class="cmdk-item" data-idx="${idx}">
                        <span class="ci-icon"><i class="fa-solid ${c.icon}"></i></span>
                        <span class="ci-text"><div class="ci-title">${c.title}</div><div class="ci-sub">${c.sub || ''}</div></span>
                    </div>`;
                });
            }

            if (term) {
                const idx = current.length;
                current.push({ url: searchBase + '?q=' + encodeURIComponent(q.trim()) });
                html += '<div class="cmdk-group-label">Pencarian</div>';
                html += `<div class="cmdk-item" data-idx="${idx}">
                    <span class="ci-icon"><i class="fa-solid fa-barcode"></i></span>
                    <span class="ci-text"><div class="ci-title">Cari "${q.trim().replace(/</g,'&lt;')}"</div><div class="ci-sub">Cari Serial Number / IMEI / Plat</div></span>
                </div>`;
            }

            if (!current.length) {
                html = '<div class="cmdk-empty">Tidak ada hasil ditemukan.</div>';
            }
            results.innerHTML = html;
            active = 0;
            highlight();

            results.querySelectorAll('.cmdk-item').forEach(el => {
                el.addEventListener('click', () => go(parseInt(el.dataset.idx, 10)));
                el.addEventListener('mousemove', () => { active = parseInt(el.dataset.idx, 10); highlight(); });
            });
        }

        function highlight() {
            results.querySelectorAll('.cmdk-item').forEach(el => {
                el.classList.toggle('active', parseInt(el.dataset.idx, 10) === active);
            });
            const activeEl = results.querySelector('.cmdk-item.active');
            if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
        }

        function go(idx) {
            if (current[idx]) window.location.href = current[idx].url;
        }

        function open() {
            overlay.classList.add('open');
            input.value = '';
            buildList('');
            setTimeout(() => input.focus(), 30);
        }
        function close() { overlay.classList.remove('open'); }

        if (trigger) trigger.addEventListener('click', open);

        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                overlay.classList.contains('open') ? close() : open();
            } else if (e.key === 'Escape' && overlay.classList.contains('open')) {
                close();
            }
        });

        input.addEventListener('input', () => buildList(input.value));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, current.length - 1); highlight(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); highlight(); }
            else if (e.key === 'Enter') { e.preventDefault(); go(active); }
        });

        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    })();
    </script>
    @yield('scripts')
</body>

</html>