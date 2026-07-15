@php
    $themeMode = \App\Models\AppSetting::getValue('theme_mode', 'dark');
    $appFavicon = \App\Models\AppSetting::getValue('app_favicon');
    $appLogo = \App\Models\AppSetting::getValue('app_logo');

    // Alert stok minimum (StockAlertThreshold) untuk ikon lonceng & badge menu.
    $authUser = auth()->user();
    $alertScope = $authUser?->isWarehouseBound()
        ? $authUser->warehouse_code
        : session('active_warehouse_code');
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
    <title>@yield('title', 'WMS - East Area Team')</title>
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

        /* ===== GLOBAL CUSTOM CONFIRM MODAL ===== */
        .custom-confirm-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            z-index: 999999 !important; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .custom-confirm-overlay.show { opacity: 1; visibility: visible; }
        .custom-confirm-box {
            background: var(--bg-secondary); border: 1px solid var(--border-color);
            border-radius: 20px; width: 90%; max-width: 380px; padding: 24px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.25);
            transform: translateY(20px) scale(0.95); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-align: center;
        }
        .custom-confirm-overlay.show .custom-confirm-box { transform: translateY(0) scale(1); }
        .custom-confirm-icon {
            width: 64px; height: 64px; border-radius: 50%; background: rgba(239,68,68,0.1);
            color: #ef4444; font-size: 28px; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .custom-confirm-title { font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px; }
        .custom-confirm-message { font-size: 14px; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.5; font-weight: 500; }
        .custom-confirm-actions { display: flex; gap: 12px; justify-content: center; }
        .custom-confirm-btn {
            padding: 10px 24px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; border: none; flex: 1;
        }
        .btn-confirm-cancel { background: var(--bg-tertiary); color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-confirm-cancel:hover { background: var(--border-color); color: var(--text-primary); }
        .btn-confirm-ok { background: linear-gradient(135deg, #ef4444, #b91c1c); color: white; box-shadow: 0 4px 12px rgba(239,68,68,0.3); }
        .btn-confirm-ok:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(239,68,68,0.4); }

        /* Override Theme Toggle size to match other header buttons */
        .theme-toggle {
            --tt-w: 72px !important;
            --tt-h: 36px !important;
        }
        .theme-toggle .tt-icon {
            font-size: 14px !important;
        }

        /* ===== GLOBAL BACKGROUND LOGO ===== */
        .bg-3d-logo-container {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .bg-3d-logo {
            width: 80vw;
            max-width: 900px;
            opacity: 0.05;
        }
        :is(.dark-theme, html[data-theme="dark"], body.dark-mode) .bg-3d-logo {
            opacity: 0.08;
        }

        /* Custom simple pagination to ensure it looks good across all pages */
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 4px;
            align-items: center;
        }
        .pagination li a, .pagination li span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination li a:hover {
            background: rgba(59,130,246,0.12);
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }
        .pagination li.active span {
            background: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
            font-weight: 600;
        }
        .pagination li.disabled span {
            opacity: 0.5;
            cursor: not-allowed;
        }

    </style>
    @yield('styles')
</head>

<body>
    <!-- Global 3D Logo Background -->
    <div class="bg-3d-logo-container">
        @if($appLogo)
            <img src="{{ asset($appLogo) }}" alt="WMS Background" class="bg-3d-logo">
        @else
            <img src="{{ asset('uploads/logo_1782107462.png') }}" alt="WMS Background" class="bg-3d-logo">
        @endif
    </div>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar collapsed" id="sidebar">
            <script>
                if (localStorage.getItem('sidebarState') === 'expanded') {
                    document.getElementById('sidebar').classList.remove('collapsed');
                } else {
                    localStorage.setItem('sidebarState', 'collapsed');
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
                @php
                    // Sidebar operasional hanya muncul jika session warehouse bertipe CABANG.
                    // Jika session = PUSAT atau GLOBAL (kosong), sembunyikan menu CRUD.
                    $activeWhType = strtolower(session('active_warehouse_type') ?? '');
                    $isViewOnlyMode = ($activeWhType !== 'cabang');
                @endphp
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

                    @if(!$isViewOnlyMode && !auth()->user()->hasRole(\App\Models\User::ROLE_QC))
                    <!-- OPERASIONAL GUDANG (alur barang masuk -> pindah -> keluar -> kembali) -->
                    <li class="nav-section"><span>Operasional Gudang</span></li>

                    @if(!auth()->user()->hasRole(\App\Models\User::ROLE_TECHNICIAN))
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
                            <span>Transfer Device</span>
                        </a>
                    </li>
                    @endif

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
                    @endif

                    @if(!auth()->user()->hasRole(\App\Models\User::ROLE_TECHNICIAN))
                    @if(!$isViewOnlyMode)
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
                    @endif
                    @if(!$isViewOnlyMode && !auth()->user()->hasRole(\App\Models\User::ROLE_QC))
                    <li class="{{ Route::currentRouteName() === 'stock.opname' ? 'active' : '' }}">
                        <a href="{{ route('stock.opname') }}" title="Stock Opname / Koreksi Stok">
                            <i class="fa-solid fa-boxes-packing"></i>
                            <span>Stock Opname Warehouse</span>
                        </a>
                    </li>
                    @endif
                    @endif {{-- end !isTechnician --}}

                    @if(!auth()->user()->hasRole(\App\Models\User::ROLE_QC, \App\Models\User::ROLE_TECHNICIAN))
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
                    <li class="{{ Route::currentRouteName() === 'warranty' ? 'active' : '' }}">
                        <a href="{{ route('warranty') }}" title="Garansi Perangkat">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Garansi Perangkat</span>
                        </a>
                    </li>
                    @endif

                    @if(auth()->user()?->hasRole(\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_ADMIN))
                    <!-- ADMINISTRASI (Super Admin & Admin) -->
                    <li class="nav-section"><span>Administrasi</span></li>
                    <li class="{{ Route::currentRouteName() === 'master_data' ? 'active' : '' }}">
                        <a href="{{ route('master_data') }}" title="Master Data">
                            <i class="fa-solid fa-database"></i>
                            <span>Master Data</span>
                        </a>
                    </li>
                    @endif

                    @if(auth()->user()?->isSuperAdmin())
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

                    <li class="nav-section"><span>Informasi</span></li>
                    <li class="{{ Route::currentRouteName() === 'about' ? 'active' : '' }}">
                        <a href="{{ route('about') }}" title="Tentang WMS Easy Go">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>About Us</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                @php($authUser = auth()->user())
                <div class="user-profile" style="display: flex; align-items: center; gap: 10px;">
                    <img src="{{ asset('images/default-avatar.png') }}"
                        onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($authUser?->name ?? "User") }}&background=4f46e5&color=fff';"
                        alt="User Avatar" class="user-avatar" style="object-fit: cover;">
                    <div class="user-info" style="flex: 1; min-width: 0;">
                        <span class="user-name">{{ $authUser?->name ?? 'Pengguna' }}</span>
                        <span class="user-role">{{ $authUser?->roleLabel() ?? '' }}</span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn-logout" title="Keluar / Logout"
                            onclick="return confirm('Yakin ingin keluar?')"
                            style="display: flex; align-items: center; gap: 6px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; border-radius: 8px; padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
                            <i class="fa-solid fa-power-off"></i> Logout
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
                <!-- Global search removed per user request -->
                <div class="header-actions">
                    <button type="button" class="cmdk-hint-btn" id="cmdkTrigger" title="Buka Command Palette">
                        <kbd>🔍</kbd>
                        <span>Search Menu</span>
                       
                    </button>
                    <button type="button" id="themeToggleBtn"
                            class="theme-toggle {{ $themeMode === 'dark' ? 'is-dark' : 'is-light' }}"
                            title="{{ $themeMode === 'dark' ? 'Beralih ke Light Mode' : 'Beralih ke Dark Mode' }}"
                            style="margin-right: 8px;"
                            data-current="{{ $themeMode }}"
                            data-url="{{ route('settings.theme') }}"
                            data-csrf="{{ csrf_token() }}">
                        <span class="tt-knob"></span>
                        <span class="tt-icon tt-sun"><i class="fa-solid fa-sun"></i></span>
                        <span class="tt-icon tt-moon"><i class="fa-solid fa-moon"></i></span>
                    </button>

                    @if($authUser?->canSelectWarehouse())
                        @if(session('active_warehouse_code'))
                        <a href="{{ route('select.warehouse') }}" class="warehouse-indicator" title="Klik untuk ganti gudang / Global" style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(99,102,241,0.10)); border: 1px solid rgba(59,130,246,0.25); border-radius: 8px; padding: 6px 14px; text-decoration: none; transition: all 0.2s ease; cursor: pointer;">
                            <i class="fa-solid fa-warehouse" style="color: var(--accent-blue); font-size: 13px;"></i>
                            <div style="display: flex; flex-direction: column; line-height: 1.2;">
                                <span style="font-size: 11px; font-weight: 600; color: var(--text-primary);">{{ session('active_warehouse_name') }}</span>
                                <span style="font-size: 9px; color: var(--text-muted); font-family: monospace;">{{ session('active_warehouse_code') }} · {{ session('active_warehouse_type') }}</span>
                            </div>
                            <i class="fa-solid fa-repeat" style="color: var(--text-muted); font-size: 10px; margin-left: 4px;"></i>
                        </a>
                        @else
                        <a href="{{ route('select.warehouse') }}" class="warehouse-indicator" title="Mode Global — klik untuk pilih gudang transaksi" style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(34,197,94,0.12), rgba(59,130,246,0.08)); border: 1px solid rgba(34,197,94,0.25); border-radius: 8px; padding: 6px 14px; text-decoration: none; transition: all 0.2s ease; cursor: pointer;">
                            <i class="fa-solid fa-earth-asia" style="color: var(--accent-emerald); font-size: 13px;"></i>
                            <div style="display: flex; flex-direction: column; line-height: 1.2;">
                                <span style="font-size: 11px; font-weight: 600; color: var(--text-primary);">Global</span>
                                <span style="font-size: 9px; color: var(--text-muted);">Semua gudang · read-only</span>
                            </div>
                            <i class="fa-solid fa-repeat" style="color: var(--text-muted); font-size: 10px; margin-left: 4px;"></i>
                        </a>
                        @endif
                    @elseif(session('active_warehouse_code'))
                    <div class="warehouse-indicator" title="Gudang terikat ke akun Anda" style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(99,102,241,0.10)); border: 1px solid rgba(59,130,246,0.25); border-radius: 8px; padding: 6px 14px; cursor: default;">
                        <i class="fa-solid fa-warehouse" style="color: var(--accent-blue); font-size: 13px;"></i>
                        <div style="display: flex; flex-direction: column; line-height: 1.2;">
                            <span style="font-size: 11px; font-weight: 600; color: var(--text-primary);">{{ session('active_warehouse_name') }}</span>
                            <span style="font-size: 9px; color: var(--text-muted); font-family: monospace;">{{ session('active_warehouse_code') }} · {{ session('active_warehouse_type') }}</span>
                        </div>
                        @if($authUser?->isWarehouseBound())
                        <i class="fa-solid fa-lock" style="color: var(--text-muted); font-size: 10px; margin-left: 4px;"></i>
                        @endif
                    </div>
                    @endif
                    <!-- Alert Notification Bell -->
                    <div class="notif-wrapper" id="notifWrapper">
                        <button type="button" class="notif-bell" id="notifBell" title="Alert Stok Minimum" aria-label="Alert">
                            <i class="fa-solid fa-bell"></i>
                            @if($alertCount > 0)
                                <span class="notif-badge" id="notifBadge">{{ $alertCount > 99 ? '99+' : $alertCount }}</span>
                            @else
                                <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                            @endif
                        </button>
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-head">
                                <span><i class="fa-solid fa-triangle-exclamation"></i> Alert Stok Minimum</span>
                                <a href="{{ route('alerts') }}">Lihat semua</a>
                            </div>
                            <div class="notif-list">
                                @forelse($stockAlerts as $a)
                                    <x-alert-item :alert="$a" variant="bell" :alertId="md5(($a['type'] ?? '') . '|' . ($a['message'] ?? '') . '|' . ($a['warehouse'] ?? ''))" />
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
                @if (session('warning'))
                    <div class="alert-box alert-warning animate-fade-in" style="background-color: var(--bg-primary); border-left: 4px solid var(--accent-amber); padding: 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                        <div class="alert-icon" style="color: var(--accent-amber); font-size: 20px; margin-top: 2px;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="alert-message" style="color: var(--text-primary); font-size: 14px; line-height: 1.5; font-weight: 500; white-space: pre-wrap;">{{ session('warning') }}</div>
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

        // Notification bell dropdown toggle + read-tracking badge
        const notifWrapper = document.getElementById('notifWrapper');
        const notifBell = document.getElementById('notifBell');
        const notifBadge = document.getElementById('notifBadge');

        // Total alert dari server
        const totalServerAlerts = parseInt('{{ $alertCount }}') || 0;

        // Ambil set alert-id yang sudah dibaca dari localStorage
        const STORAGE_KEY = 'wms_read_alert_ids';
        function getReadIds() {
            try { return new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')); }
            catch(e) { return new Set(); }
        }
        function saveReadIds(set) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify([...set]));
        }

        // Hitung ulang badge berdasarkan item di DOM yang belum dibaca
        function recalcBadge() {
            if (!notifBadge) return;
            const readIds = getReadIds();
            const allItems = document.querySelectorAll('.notif-item[data-alert-id]');
            let unread = 0;
            allItems.forEach(item => {
                const id = item.dataset.alertId;
                if (id && !readIds.has(id)) unread++;
            });
            // Jika tidak ada item dengan data-alert-id (fallback), gunakan totalServerAlerts
            if (allItems.length === 0) unread = totalServerAlerts;

            if (unread > 0) {
                notifBadge.textContent = unread > 99 ? '99+' : unread;
                notifBadge.style.display = '';
            } else {
                notifBadge.style.display = 'none';
            }
        }

        // Tandai item sebagai read saat diklik, lalu update badge
        function attachNotifReadListeners() {
            document.querySelectorAll('.notif-item[data-alert-id]').forEach(item => {
                item.addEventListener('click', () => {
                    const id = item.dataset.alertId;
                    if (!id) return;
                    const readIds = getReadIds();
                    if (!readIds.has(id)) {
                        readIds.add(id);
                        saveReadIds(readIds);
                        item.style.opacity = '0.5'; // visual feedback: sudah dibaca
                        recalcBadge();
                    }
                });
            });
        }

        // Terapkan visual "sudah dibaca" saat dropdown dibuka
        function applyReadState() {
            const readIds = getReadIds();
            document.querySelectorAll('.notif-item[data-alert-id]').forEach(item => {
                if (readIds.has(item.dataset.alertId)) {
                    item.style.opacity = '0.5';
                }
            });
        }

        if (notifBell && notifWrapper) {
            // Hitung badge saat halaman dimuat
            recalcBadge();
            attachNotifReadListeners();

            notifBell.addEventListener('click', (e) => {
                e.stopPropagation();
                notifWrapper.classList.toggle('open');
                if (notifWrapper.classList.contains('open')) {
                    applyReadState();
                }
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

    <script>
        // JS Tooltip for Sidebar (Appends to body to avoid clipping)
        document.addEventListener('DOMContentLoaded', () => {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            document.body.appendChild(tooltip);

            const sidebarEl = document.getElementById('sidebar');

            document.querySelectorAll('.sidebar-nav a').forEach(a => {
                a.addEventListener('mouseenter', () => {
                    // Hanya tampilkan custom tooltip jika sidebar dalam mode collapsed
                    if (!sidebarEl.classList.contains('collapsed')) return;
                    
                    const span = a.querySelector('span:not(.nav-badge)');
                    if (span) {
                        // Hilangkan native tooltip (title) untuk menghindari double tooltip
                        if (a.hasAttribute('title')) {
                            a.dataset.originalTitle = a.getAttribute('title');
                            a.removeAttribute('title');
                        }
                        
                        tooltip.textContent = span.textContent;
                        tooltip.classList.add('show');
                        const rect = a.getBoundingClientRect();
                        tooltip.style.top = (rect.top + rect.height / 2) + 'px';
                        tooltip.style.left = (rect.right + 10) + 'px';
                    }
                });
                
                a.addEventListener('mouseleave', () => {
                    tooltip.classList.remove('show');
                    // Kembalikan native tooltip saat mouse pergi (opsional, untuk safety)
                    if (a.dataset.originalTitle) {
                        a.setAttribute('title', a.dataset.originalTitle);
                    }
                });
            });
            
            // Sembunyikan tooltip saat sidebar di-expand secara manual
            const toggleBtn = document.getElementById('sidebarToggle');
            if(toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    tooltip.classList.remove('show');
                });
            }
        });
    </script>

    <!-- COMMAND PALETTE LOGIC -->
    <script>
    (function () {
        const navCommands = [
            { title: 'Dashboard', sub: 'Ringkasan & monitoring', icon: 'fa-chart-pie', url: @json(route('dashboard')) },
            { title: 'Alert Center', sub: 'Peringatan stok minimum', icon: 'fa-bell', url: @json(route('alerts')) },
            { title: 'Penerimaan (Receiving)', sub: 'Scan barang masuk', icon: 'fa-file-import', url: @json(route('receiving')) },
            { title: 'Transfer Device', sub: 'Pindah antar gudang', icon: 'fa-truck-ramp-box', url: @json(route('transfer')) },
            { title: 'Serah Terima', sub: 'Serahkan ke teknisi/customer', icon: 'fa-user-gear', url: @json(route('issue')) },
            { title: 'Return Perangkat', sub: 'Pengembalian ke gudang', icon: 'fa-boxes-packing', url: @json(route('return')) },
            { title: 'Quality Control', sub: 'QC barang masuk, return & laporan', icon: 'fa-clipboard-check', url: @json(route('quality.control')) },
            { title: 'QC Return / Inspeksi', sub: 'Inspeksi perangkat return', icon: 'fa-magnifying-glass-chart', url: @json(route('quality.control', ['tab' => 'return'])) },
            { title: 'Laporan QC', sub: 'Reject rate, lead time, throughput', icon: 'fa-chart-column', url: @json(route('quality.control', ['tab' => 'report'])) },
            { title: 'Stock Opname', sub: 'Koreksi stok', icon: 'fa-scale-balanced', url: @json(route('stock.opname')) },
            { title: 'Search & Audit', sub: 'Pencarian & jejak audit', icon: 'fa-magnifying-glass', url: @json(route('search')) },
            { title: 'Laporan & Analitik', sub: 'Laporan & ekspor', icon: 'fa-chart-line', url: @json(route('reports')) },
            @if(auth()->user()?->isSuperAdmin())
            { title: 'Ganti Gudang / Global', sub: 'Pilih gudang transaksi atau mode Global', icon: 'fa-warehouse', url: @json(route('select.warehouse')) },
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

    <!-- Global Custom Confirm Modal -->
    <div class="custom-confirm-overlay" id="globalConfirmModal">
        <div class="custom-confirm-box">
            <div class="custom-confirm-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="custom-confirm-title">Konfirmasi</div>
            <div class="custom-confirm-message" id="globalConfirmMessage">Apakah Anda yakin?</div>
            <div class="custom-confirm-actions">
                <button type="button" class="custom-confirm-btn btn-confirm-cancel" id="globalConfirmCancel">Batal</button>
                <button type="button" class="custom-confirm-btn btn-confirm-ok" id="globalConfirmOk">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const modal = document.getElementById('globalConfirmModal');
        const msgEl = document.getElementById('globalConfirmMessage');
        const btnOk = document.getElementById('globalConfirmOk');
        const btnCancel = document.getElementById('globalConfirmCancel');
        let currentCallback = null;

        window.showCustomConfirm = function(message, callback) {
            msgEl.textContent = message;
            currentCallback = callback;
            modal.classList.add('show');
            if(window.playBeep) window.playBeep('error');
        };

        function closeConfirm() {
            modal.classList.remove('show');
            currentCallback = null;
        }

        btnCancel.addEventListener('click', closeConfirm);
        btnOk.addEventListener('click', () => {
            if (currentCallback) currentCallback();
            closeConfirm();
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[onclick*="confirm("]').forEach(el => {
                const onclickStr = el.getAttribute('onclick');
                const match = onclickStr.match(/confirm\(['"](.*?)['"]\)/);
                if (match) {
                    const message = match[1];
                    el.setAttribute('data-confirm', message);
                    el.removeAttribute('onclick');
                    
                    el.addEventListener('click', function(e) {
                        e.preventDefault();
                        showCustomConfirm(message, () => {
                            if (el.tagName === 'BUTTON' && el.type === 'submit') {
                                const form = el.closest('form');
                                if (form) form.submit();
                            } else if (el.tagName === 'A') {
                                window.location.href = el.href;
                            }
                        });
                    });
                }
            });

            // Global interception for forms with POST/PUT/DELETE method (creation/saving)
            document.addEventListener('submit', function(e) {
                const form = e.target;
                
                // Skip GET forms (like search filters)
                if (!form.method || form.method.toUpperCase() === 'GET') return;
                
                // Skip if the form has a data-no-confirm attribute
                if (form.hasAttribute('data-no-confirm')) return;
                
                // Skip if we already confirmed
                if (form.dataset.confirmed === 'true') {
                    form.dataset.confirmed = 'false'; // Reset for future submissions
                    return;
                }
                
                e.preventDefault();
                
                const message = form.getAttribute('data-confirm') || 'Apakah Anda yakin ingin melanjutkan dan menyimpan data ini?';
                
                showCustomConfirm(message, function() {
                    form.dataset.confirmed = 'true';
                    form.submit();
                });
            });
        });
    })();
    </script>

    @yield('scripts')

    <!-- ===== THEME TOGGLE: AJAX (no redirect) + Toast Popup ===== -->
    <style>
        #theme-toast {
            position: fixed;
            top: 72px;
            left: 50%;
            transform: translateX(-50%) translateY(-12px);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            z-index: 99999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        #theme-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        #theme-toast .toast-icon { color: #10b981; font-size: 15px; }
    </style>

    <div id="theme-toast">
        <i class="fa-solid fa-circle-check toast-icon"></i>
        <span id="theme-toast-msg">Tema berhasil diubah</span>
    </div>

    <script>
    (function () {
        const btn = document.getElementById('themeToggleBtn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            const current  = btn.getAttribute('data-current');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            const url      = btn.getAttribute('data-url');
            const csrf     = btn.getAttribute('data-csrf');

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ theme_mode: newTheme }),
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                // Apply theme change immediately on the HTML element
                document.documentElement.classList.remove('dark-theme', 'light-theme');
                document.documentElement.classList.add(newTheme + '-theme');

                // Update toggle state
                btn.setAttribute('data-current', newTheme);
                btn.classList.remove('is-dark', 'is-light');
                btn.classList.add(newTheme === 'dark' ? 'is-dark' : 'is-light');
                btn.title = newTheme === 'dark' ? 'Beralih ke Light Mode' : 'Beralih ke Dark Mode';

                // Show toast popup
                const toast = document.getElementById('theme-toast');
                const msg   = document.getElementById('theme-toast-msg');
                if (toast && msg) {
                    msg.textContent = data.message || 'Tema berhasil diubah';
                    toast.classList.add('show');
                    setTimeout(() => toast.classList.remove('show'), 1000);
                }
            })
            .catch(() => {
                // Fallback: reload the page so theme is applied from server
                window.location.reload();
            });
        });
    })();

    // Tangkap validasi "required" HTML5 yang gagal agar bisa muncul popup alert custom
    document.addEventListener('invalid', (function () {
        return function (e) {
            e.preventDefault(); // Mencegah tooltip bawaan browser
            
            // Dapatkan nama label atau nama input
            let fieldName = e.target.name || 'Bidang';
            let label = document.querySelector('label[for="' + e.target.id + '"]');
            if (label && label.innerText) {
                fieldName = label.innerText.replace('*', '').trim();
            }

            // Munculkan custom alert menggunakan showCustomConfirm (tanpa action form)
            if (window.showCustomConfirm) {
                window.showCustomConfirm('Input "' + fieldName + '" belum diisi atau formatnya salah. Mohon lengkapi sebelum menyimpan.', function() {
                    e.target.focus({ preventScroll: true });
                });
            } else {
                alert('Input "' + fieldName + '" belum diisi. Mohon lengkapi sebelum menyimpan.');
                e.target.focus({ preventScroll: true });
            }
        };
    })(), true);

    // ============================================================
    // GLOBAL TABLE ENHANCER — Sorting + Filter Bar
    // Applies to every <table class="table"> in the entire system
    // Skips tables already managed by DataTables (has class "dataTable")
    // ============================================================
    (function () {
        // Run after all page scripts have loaded (including DataTables init)
        window.addEventListener('load', function () {
            enhanceAllTables();
        });

        // Also re-run if dynamic content is added (e.g. modal, tab reveal)
        document.addEventListener('click', function () {
            setTimeout(enhanceAllTables, 300);
        });

        function enhanceAllTables() {
            document.querySelectorAll('table.table').forEach(function (tbl) {
                // Skip if already enhanced or managed by DataTables
                if (tbl.classList.contains('dt-enhanced') || tbl.classList.contains('dataTable')) return;
                tbl.classList.add('dt-enhanced');

                // ---- 1. INJECT FILTER BAR ABOVE TABLE ----
                const wrapper = tbl.closest('.table-wrapper') || tbl.parentElement;
                if (wrapper && !wrapper.querySelector('.auto-table-filter-bar')) {
                    const bar = document.createElement('div');
                    bar.className = 'auto-table-filter-bar';
                    bar.style.cssText = 'display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap;';
                    bar.innerHTML = `
                        <div style="position:relative; flex:1; min-width:200px; max-width:340px;">
                            <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:12px;"></i>
                            <input type="text" class="form-control auto-table-search" placeholder="Cari di tabel..." style="padding-left:32px; height:34px; font-size:12px;" autocomplete="off">
                        </div>
                        <span class="auto-table-count" style="font-size:12px; color:var(--text-muted);"></span>
                    `;
                    wrapper.insertBefore(bar, tbl);

                    const searchInput = bar.querySelector('.auto-table-search');
                    const countSpan   = bar.querySelector('.auto-table-count');

                    searchInput.addEventListener('input', function () {
                        filterTable(tbl, this.value.toLowerCase(), countSpan);
                    });

                    updateCount(tbl, countSpan);
                }

                // ---- 2. ADD SORT ARROWS ON TH CLICK ----
                const thead = tbl.querySelector('thead');
                if (!thead) return;

                // Only target the FIRST header row (skip filter-row if present)
                const headerRow = thead.querySelector('tr');
                if (!headerRow) return;

                Array.from(headerRow.querySelectorAll('th')).forEach(function (th, colIndex) {
                    if (th.querySelector('.sort-arrow')) return; // already done

                    th.style.cursor = 'pointer';
                    th.style.userSelect = 'none';
                    th.style.whiteSpace = 'nowrap';

                    const arrow = document.createElement('span');
                    arrow.className = 'sort-arrow';
                    arrow.style.cssText = 'margin-left:5px; font-size:10px; opacity:0.4;';
                    arrow.textContent = '⇅';
                    th.appendChild(arrow);

                    let sortDir = 0; // 0=none, 1=asc, -1=desc

                    th.addEventListener('click', function () {
                        // Reset all other columns
                        headerRow.querySelectorAll('th .sort-arrow').forEach(function (a) {
                            a.textContent = '⇅';
                            a.style.opacity = '0.4';
                        });

                        sortDir = (sortDir === 1) ? -1 : 1;
                        arrow.textContent = sortDir === 1 ? '↑' : '↓';
                        arrow.style.opacity = '1';

                        sortTable(tbl, colIndex, sortDir);
                    });
                });
            });
        }

        function sortTable(tbl, colIndex, direction) {
            const tbody = tbl.querySelector('tbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr')).filter(function (r) {
                return !r.id || !r.id.includes('EmptyPlaceholder');
            });
            rows.sort(function (a, b) {
                const aText = (a.cells[colIndex] ? a.cells[colIndex].textContent : '').trim().toLowerCase();
                const bText = (b.cells[colIndex] ? b.cells[colIndex].textContent : '').trim().toLowerCase();
                const aNum = parseFloat(aText.replace(/[^0-9.\-]/g, ''));
                const bNum = parseFloat(bText.replace(/[^0-9.\-]/g, ''));
                if (!isNaN(aNum) && !isNaN(bNum)) return direction * (aNum - bNum);
                return direction * aText.localeCompare(bText, 'id');
            });
            rows.forEach(function (r) { tbody.appendChild(r); });
        }

        function filterTable(tbl, query, countSpan) {
            const tbody = tbl.querySelector('tbody');
            if (!tbody) return;
            let visible = 0;
            Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const show = !query || text.includes(query);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            updateCount(tbl, countSpan, query ? visible : null);
        }

        function updateCount(tbl, span, visible) {
            if (!span) return;
            const tbody = tbl.querySelector('tbody');
            if (!tbody) return;
            const total = tbody.querySelectorAll('tr').length;
            span.textContent = visible !== null && visible !== undefined
                ? visible + ' dari ' + total + ' baris'
                : total + ' baris';
        }
    })();
    </script>
</body>

</html>