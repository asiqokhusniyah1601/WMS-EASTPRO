@php
    $appLogo = \App\Models\AppSetting::getValue('app_logo');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan {{ $fromDate }} s/d {{ $toDate }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1f2937; margin: 0; padding: 32px; background: #f3f4f6; font-size: 13px; }
        .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 36px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #4f46e5; padding-bottom: 16px; margin-bottom: 24px; }
        .doc-header h1 { font-size: 20px; margin: 0 0 4px; }
        .doc-header .meta { font-size: 12px; color: #6b7280; }
        .doc-header .logo { width: 48px; height: 48px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6, #4f46e5); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 22px; }
        .doc-header .logo img { width: 30px; height: 30px; object-fit: contain; }
        h2.section { font-size: 15px; margin: 28px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; color: #4f46e5; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 8px; }
        .kpi { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .kpi .label { font-size: 11px; color: #6b7280; }
        .kpi .value { font-size: 22px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; font-size: 12px; }
        th { background: #f9fafb; font-weight: 600; }
        .text-center { text-align: center; }
        .muted { color: #9ca3af; font-style: italic; }
        .toolbar { max-width: 900px; margin: 0 auto 16px; text-align: right; }
        .btn-print { background: #4f46e5; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; cursor: pointer; }
        .doc-footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #9ca3af; text-align: center; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: 100%; padding: 0; }
            .toolbar { display: none; }
            h2.section { page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Cetak / Simpan PDF</button>
    </div>

    <div class="sheet">
        <div class="doc-header">
            <div>
                <h1>Laporan Operasional Gudang</h1>
                <div class="meta">
                    Periode: <strong>{{ $fromDate }}</strong> s/d <strong>{{ $toDate }}</strong><br>
                    Gudang: <strong>{{ $warehouseLabel }}</strong> &middot; Dicetak: {{ now()->format('d M Y H:i') }}
                </div>
            </div>
            <div class="logo">
                @if($appLogo)<img src="{{ asset($appLogo) }}" alt="Logo">@else<i class="fa-solid fa-boxes-stacked"></i>@endif
            </div>
        </div>

        <h2 class="section">1. Ringkasan Eksekutif</h2>
        <div class="kpi-grid">
            <div class="kpi"><div class="label">Barang Masuk</div><div class="value" style="color:#059669;">{{ $executive['total_in'] }}</div></div>
            <div class="kpi"><div class="label">Barang Keluar</div><div class="value" style="color:#d97706;">{{ $executive['total_out'] }}</div></div>
            <div class="kpi"><div class="label">Net Mutasi</div><div class="value" style="color:#2563eb;">{{ $executive['net'] }}</div></div>
            <div class="kpi"><div class="label">Total Perangkat</div><div class="value">{{ $executive['total_devices'] }}</div></div>
        </div>
        <table>
            <thead><tr><th>Status Perangkat</th><th class="text-center">Jumlah</th></tr></thead>
            <tbody>
                @forelse($executive['status_snapshot'] as $status => $count)
                    <tr><td>{{ $status }}</td><td class="text-center">{{ $count }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2 class="section">2. Mutasi Barang (In/Out) per Periode</h2>
        <table>
            <thead><tr><th>Periode</th><th class="text-center">Masuk</th><th class="text-center">Keluar</th><th class="text-center">Net</th></tr></thead>
            <tbody>
                @forelse($movement['labels'] as $i => $label)
                    <tr><td>{{ $label }}</td><td class="text-center">{{ $movement['in'][$i] }}</td><td class="text-center">{{ $movement['out'][$i] }}</td><td class="text-center">{{ $movement['net'][$i] }}</td></tr>
                @empty
                    <tr><td colspan="4" class="muted">Tidak ada transaksi pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2 class="section">3. Aset Aktif per Teknisi</h2>
        <table>
            <thead><tr><th>Kode</th><th>Pemegang</th><th class="text-center">GPS</th><th class="text-center">MDVR</th><th class="text-center">Dashcam</th><th class="text-center">Total</th></tr></thead>
            <tbody>
                @forelse($technicianStock['devices'] as $t)
                    <tr><td>{{ $t['code'] }}</td><td>{{ $t['name'] }}</td><td class="text-center">{{ $t['gps'] }}</td><td class="text-center">{{ $t['mdvr'] }}</td><td class="text-center">{{ $t['dashcam'] }}</td><td class="text-center">{{ $t['total'] }}</td></tr>
                @empty
                    <tr><td colspan="6" class="muted">Tidak ada perangkat di tangan teknisi.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2 class="section">4. Dead Stock (di gudang > 30 hari)</h2>
        <table>
            <thead><tr><th>Serial</th><th>Tipe</th><th>Gudang</th><th class="text-center">Umur (hari)</th><th>Pergerakan Terakhir</th></tr></thead>
            <tbody>
                @forelse(array_slice($aging['dead_stock'], 0, 40) as $d)
                    <tr><td>{{ $d['serial_number'] }}</td><td>{{ $d['type'] }}</td><td>{{ $d['warehouse'] }}</td><td class="text-center">{{ $d['age_days'] }}</td><td>{{ $d['last_movement'] }}</td></tr>
                @empty
                    <tr><td colspan="5" class="muted">Tidak ada dead stock.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2 class="section">5. Kualitas (QC, Repair, Scrap)</h2>
        <div class="kpi-grid">
            <div class="kpi"><div class="label">Total Inspeksi</div><div class="value">{{ $quality['total_inspections'] }}</div></div>
            <div class="kpi"><div class="label">QC Gagal</div><div class="value" style="color:#dc2626;">{{ $quality['qc_failed'] }}</div></div>
            <div class="kpi"><div class="label">Sedang Repair</div><div class="value">{{ $quality['current_repair'] }}</div></div>
            <div class="kpi"><div class="label">Scrap</div><div class="value">{{ $quality['current_scrap'] }}</div></div>
        </div>

        <h2 class="section">6. Audit Koreksi Manual</h2>
        <table>
            <thead><tr><th>Serial</th><th>Dari</th><th>Ke</th><th>Operator</th><th>Alasan</th><th>Tanggal</th></tr></thead>
            <tbody>
                @forelse(array_slice($adjustment['device_adjustments'], 0, 40) as $a)
                    <tr><td>{{ $a['device_sn'] }}</td><td>{{ $a['from'] }}</td><td>{{ $a['to'] }}</td><td>{{ $a['operator'] }}</td><td>{{ $a['notes'] }}</td><td>{{ $a['created_at'] }}</td></tr>
                @empty
                    <tr><td colspan="6" class="muted">Tidak ada koreksi pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="doc-footer">
            Dokumen ini dihasilkan otomatis oleh WMS EASTPRO &middot; PT EasyGo Indonesia
        </div>
    </div>

    <script>
        // Auto-buka dialog cetak bila diminta via ?autoprint=1
        if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
            window.addEventListener('load', () => setTimeout(() => window.print(), 400));
        }
    </script>
</body>
</html>
