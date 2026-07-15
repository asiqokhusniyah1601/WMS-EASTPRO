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
        <!-- DATABASE BACKUP SECTION -->
        <!-- =============================== -->
        <div class="card" style="height: 100%; display: flex; flex-direction: column;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-database"></i> Backup Database</div>
            </div>
            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 16px;">
                <p style="color: var(--text-secondary); font-size: 13px; margin: 0; line-height: 1.5;">
                    Unduh cadangan (backup) seluruh isi database dalam format <strong>.sql</strong> (struktur tabel + data).
                    File dapat digunakan untuk restore via phpMyAdmin atau <code>mysql &lt; file.sql</code>. Simpan di tempat aman.
                </p>
                <div>
                    <a href="{{ route('settings.backup') }}" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-download"></i> Download Backup (.sql)
                    </a>
                </div>
            </div>
        </div>

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


</script>
@endsection
