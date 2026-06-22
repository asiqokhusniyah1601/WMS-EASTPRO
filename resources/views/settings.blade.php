@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi | DLMS')

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-gear"
        title="Pengaturan Aplikasi"
        subtitle="Kelola tampilan dan branding aplikasi WMS Anda." />

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">

        <!-- =============================== -->
        <!-- BRANDING SECTION (Logo + Favicon, compact) -->
        <!-- =============================== -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-image"></i> Branding</div>
            </div>

            <!-- Logo row -->
            <div class="brand-row">
                <div class="brand-thumb" id="logoPreviewBox">
                    @if($currentLogo)
                        <img src="{{ asset($currentLogo) }}" alt="Logo" id="logoPreviewImg">
                    @else
                        <i class="fa-solid fa-boxes-stacked" id="logoPlaceholder"></i>
                    @endif
                </div>
                <div class="brand-row-info">
                    <div class="b-title">Logo Aplikasi</div>
                    <div class="b-hint">Sidebar &middot; PNG/JPG/SVG &middot; maks 2MB</div>
                </div>
                <form action="{{ route('settings.logo') }}" method="POST" enctype="multipart/form-data" class="brand-row-form">
                    @csrf
                    <input type="file" name="logo" id="logo_file" class="form-control form-control-sm" accept="image/png,image/jpeg,image/svg+xml" required style="max-width: 150px;" onchange="previewFile(this, 'logoPreviewImg', 'logoPreviewBox')">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i></button>
                </form>
            </div>

            <!-- Favicon row -->
            <div class="brand-row">
                <div class="brand-thumb" id="faviconPreviewBox">
                    @if($currentFavicon)
                        <img src="{{ asset($currentFavicon) }}" alt="Favicon" id="faviconPreviewImg">
                    @else
                        <i class="fa-solid fa-globe" id="faviconPlaceholder"></i>
                    @endif
                </div>
                <div class="brand-row-info">
                    <div class="b-title">Favicon Browser</div>
                    <div class="b-hint">Tab browser &middot; ICO/PNG 32&times;32 &middot; maks 1MB</div>
                </div>
                <form action="{{ route('settings.favicon') }}" method="POST" enctype="multipart/form-data" class="brand-row-form">
                    @csrf
                    <input type="file" name="favicon" id="favicon_file" class="form-control form-control-sm" accept="image/x-icon,image/png,image/jpeg" required style="max-width: 150px;" onchange="previewFile(this, 'faviconPreviewImg', 'faviconPreviewBox')">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i></button>
                </form>
            </div>
        </div>

        <!-- =============================== -->
        <!-- THEME MODE SECTION -->
        <!-- =============================== -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-palette"></i> Tema Tampilan</div>
            </div>
            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 24px;">
                Geser untuk berganti tema. Perubahan berlaku di semua halaman aplikasi.
            </p>

            <div class="theme-toggle-wrap">
                <form action="{{ route('settings.theme') }}" method="POST">
                    @csrf
                    <input type="hidden" name="theme_mode" value="{{ $themeMode === 'dark' ? 'light' : 'dark' }}">
                    <button type="submit" class="theme-toggle {{ $themeMode === 'dark' ? 'is-dark' : 'is-light' }}"
                            title="{{ $themeMode === 'dark' ? 'Beralih ke Light Mode' : 'Beralih ke Dark Mode' }}">
                        <span class="tt-knob"></span>
                        <span class="tt-icon tt-sun"><i class="fa-solid fa-sun"></i></span>
                        <span class="tt-icon tt-moon"><i class="fa-solid fa-moon"></i></span>
                    </button>
                </form>
                <div class="theme-toggle-status">
                    {{ $themeMode === 'dark' ? 'Dark Mode' : 'Light Mode' }}
                    <small>{{ $themeMode === 'dark' ? 'Tema gelap, nyaman di mata' : 'Tema terang, jelas & cerah' }}</small>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================== -->
    <!-- DATABASE BACKUP SECTION -->
    <!-- =============================== -->
    <div class="card" style="margin-top: 24px;">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-database"></i> Backup Database</div>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <p style="color: var(--text-secondary); font-size: 13px; margin: 0; max-width: 560px;">
                Unduh cadangan (backup) seluruh isi database dalam format <strong>.sql</strong> (struktur tabel + data).
                File dapat digunakan untuk restore via phpMyAdmin atau <code>mysql &lt; file.sql</code>. Simpan di tempat aman.
            </p>
            <a href="{{ route('settings.backup') }}" class="btn btn-primary">
                <i class="fa-solid fa-download"></i> Download Backup (.sql)
            </a>
        </div>
    </div>

    <!-- =============================== -->
    <!-- STOCK ALERTS SECTION -->
    <!-- =============================== -->
    <div class="card" style="margin-top: 24px;">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-bell"></i> Minimum Stock Alerts</div>
        </div>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 24px;">
            Atur batas minimum stok untuk setiap gudang. Jika stok menyentuh angka ini atau di bawahnya, sistem akan memberikan peringatan (AI Trend Insights) di Dashboard.
        </p>

        <form action="{{ route('settings.alerts') }}" method="POST">
            @csrf
            
            <div style="display: flex; gap: 16px; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; overflow-x: auto; padding-bottom: 8px;">
                @foreach($warehouses as $index => $wh)
                    <button type="button" class="btn btn-outline alert-tab-btn {{ $index === 0 ? 'active' : '' }}" data-target="alert-wh-{{ $wh->code }}" style="{{ $index === 0 ? 'border-color: var(--accent-blue); background: rgba(59,130,246,0.1); color: var(--text-primary);' : '' }}">
                        <i class="fa-solid fa-warehouse"></i> {{ $wh->name }}
                    </button>
                @endforeach
            </div>

            @foreach($warehouses as $index => $wh)
                @php
                    $whThresholds = isset($thresholds[$wh->code]) ? $thresholds[$wh->code]->keyBy(function($t) { return $t->item_type . '_' . $t->item_identifier; }) : collect();
                @endphp
                <div class="alert-wh-panel" id="alert-wh-{{ $wh->code }}" style="display: {{ $index === 0 ? 'block' : 'none' }};">
                    <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">
                        <i class="fa-solid fa-layer-group" style="color: var(--accent-blue);"></i> Stok Minimum di {{ $wh->name }}
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;">
                        <!-- DEVICES -->
                        <div>
                            <div style="font-weight: 600; margin-bottom: 12px; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <i class="fa-solid fa-microchip"></i> Devices
                            </div>
                            @foreach($deviceModels as $dm)
                                @php 
                                    $key = 'DEVICE_' . $dm->model_name; 
                                    $val = isset($whThresholds[$key]) ? $whThresholds[$key]->min_stock_level : 0;
                                @endphp
                                <div class="form-group">
                                    <label style="font-size: 12px;">{{ $dm->model_name }}</label>
                                    <input type="number" name="alerts[{{ $wh->code }}][DEVICE][{{ $dm->model_name }}]" class="form-control form-control-sm" value="{{ $val }}" min="0">
                                </div>
                            @endforeach
                        </div>

                        <!-- ACCESSORIES -->
                        <div>
                            <div style="font-weight: 600; margin-bottom: 12px; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <i class="fa-solid fa-plug"></i> Accessories
                            </div>
                            @foreach($accessories as $acc)
                                @php 
                                    $key = 'ACCESSORY_' . $acc->code; 
                                    $val = isset($whThresholds[$key]) ? $whThresholds[$key]->min_stock_level : 0;
                                @endphp
                                <div class="form-group">
                                    <label style="font-size: 12px;">{{ $acc->name }}</label>
                                    <input type="number" name="alerts[{{ $wh->code }}][ACCESSORY][{{ $acc->code }}]" class="form-control form-control-sm" value="{{ $val }}" min="0">
                                </div>
                            @endforeach
                        </div>

                        <!-- SIMCARDS -->
                        <div>
                            <div style="font-weight: 600; margin-bottom: 12px; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <i class="fa-solid fa-sim-card"></i> SIM Cards
                            </div>
                            @foreach($simcards as $sim)
                                @php 
                                    $key = 'SIMCARD_' . $sim->provider; 
                                    $val = isset($whThresholds[$key]) ? $whThresholds[$key]->min_stock_level : 0;
                                @endphp
                                <div class="form-group">
                                    <label style="font-size: 12px;">{{ $sim->provider }}</label>
                                    <input type="number" name="alerts[{{ $wh->code }}][SIMCARD][{{ $sim->provider }}]" class="form-control form-control-sm" value="{{ $val }}" min="0">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            <div style="margin-top: 24px; text-align: right;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Simpan Pengaturan Stok Minimum
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@section('scripts')
<script>
    function previewFile(input, imgId, boxId) {
        const box = document.getElementById(boxId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Remove placeholder icon if present
                const placeholder = box.querySelector('i');
                if (placeholder) placeholder.remove();

                let img = document.getElementById(imgId);
                if (!img) {
                    img = document.createElement('img');
                    img.id = imgId;
                    box.appendChild(img);
                }
                img.src = e.target.result;
                img.alt = 'Preview';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Tab switching for Stock Alerts
    document.querySelectorAll('.alert-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active state from all buttons
            document.querySelectorAll('.alert-tab-btn').forEach(b => {
                b.classList.remove('active');
                b.style.borderColor = '';
                b.style.background = '';
                b.style.color = '';
            });

            // Hide all panels
            document.querySelectorAll('.alert-wh-panel').forEach(p => {
                p.style.display = 'none';
            });

            // Set active state on clicked button
            this.classList.add('active');
            this.style.borderColor = 'var(--accent-blue)';
            this.style.background = 'rgba(59,130,246,0.1)';
            this.style.color = 'var(--text-primary)';

            // Show target panel
            const targetId = this.getAttribute('data-target');
            document.getElementById(targetId).style.display = 'block';
        });
    });
</script>
@endsection
