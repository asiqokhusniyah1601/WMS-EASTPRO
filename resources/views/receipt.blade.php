@php
    $appLogo = \App\Models\AppSetting::getValue('app_logo');
    $issued = \Illuminate\Support\Carbon::parse($issuedAt);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanda Terima {{ $receiptNo }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1f2937; margin: 0; padding: 28px; background: #f3f4f6; font-size: 13px; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #4f46e5; padding-bottom: 18px; margin-bottom: 8px; }
        .company { display: flex; gap: 14px; align-items: center; }
        .company .logo { width: 52px; height: 52px; border-radius: 12px; background: linear-gradient(135deg, #3b82f6, #4f46e5); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; }
        .company .logo img { width: 34px; height: 34px; object-fit: contain; }
        .company h1 { font-size: 18px; margin: 0; }
        .company p { font-size: 11px; color: #6b7280; margin: 2px 0 0; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 20px; margin: 0; color: #4f46e5; letter-spacing: 1px; }
        .doc-title .no { font-size: 12px; color: #6b7280; margin-top: 4px; font-family: monospace; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 24px 0; }
        .meta-box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; }
        .meta-box h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin: 0 0 8px; }
        .meta-box .row { display: flex; gap: 8px; margin-bottom: 4px; font-size: 13px; }
        .meta-box .row .k { color: #6b7280; min-width: 90px; }
        .meta-box .row .v { font-weight: 600; }
        h3.section { font-size: 13px; margin: 22px 0 8px; color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 7px 9px; text-align: left; font-size: 12px; }
        th { background: #f9fafb; font-weight: 600; }
        td.center, th.center { text-align: center; }
        .muted { color: #9ca3af; font-style: italic; }
        .note { font-size: 11px; color: #6b7280; margin-top: 16px; line-height: 1.6; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 48px; }
        .sign-box { text-align: center; }
        .sign-box .role { font-size: 12px; color: #6b7280; margin-bottom: 64px; }
        .sign-box .line { border-top: 1px solid #374151; padding-top: 6px; font-weight: 600; font-size: 13px; }
        .toolbar { max-width: 800px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; }
        .toolbar a { color: #4f46e5; text-decoration: none; font-size: 14px; }
        .btn-print { background: #4f46e5; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: 100%; padding: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
        <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Cetak / Simpan PDF</button>
    </div>

    <div class="sheet">
        <div class="doc-header">
            <div class="company">
                <div class="logo">
                    @if($appLogo)<img src="{{ asset($appLogo) }}" alt="Logo">@else<i class="fa-solid fa-boxes-stacked"></i>@endif
                </div>
                <div>
                    <h1>WMS EASTPRO</h1>
                    <p>PT EasyGo Indonesia</p>
                </div>
            </div>
            <div class="doc-title">
                <h2>TANDA TERIMA</h2>
                <div class="no">{{ $receiptNo }}</div>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-box">
                <h3>Diserahkan Kepada ({{ $recipientType }})</h3>
                <div class="row"><span class="k">Nama</span><span class="v">{{ $recipientName }}</span></div>
                @foreach($recipientMeta as $k => $v)
                    <div class="row"><span class="k">{{ $k }}</span><span class="v">{{ $v }}</span></div>
                @endforeach
            </div>
            <div class="meta-box">
                <h3>Informasi Penyerahan</h3>
                <div class="row"><span class="k">Tanggal</span><span class="v">{{ $issued->translatedFormat('d F Y') }}</span></div>
                <div class="row"><span class="k">Jam</span><span class="v">{{ $issued->format('H:i') }} WIB</span></div>
                <div class="row"><span class="k">Gudang Asal</span><span class="v">{{ $warehouseName }}</span></div>
                <div class="row"><span class="k">Petugas</span><span class="v">{{ $operator }}</span></div>
            </div>
        </div>

        @if(!empty($deviceItems))
            <h3 class="section">A. Daftar Perangkat ({{ count($deviceItems) }} unit)</h3>
            <table>
                <thead>
                    <tr>
                        <th class="center" style="width: 36px;">No</th>
                        <th>Serial Number</th>
                        <th>Tipe</th>
                        <th>Model</th>
                        <th>IMEI</th>
                        <th>No. Kendaraan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deviceItems as $i => $d)
                        <tr>
                            <td class="center">{{ $i + 1 }}</td>
                            <td style="font-family: monospace;">{{ $d['serial_number'] }}</td>
                            <td>{{ $d['type'] }}</td>
                            <td>{{ $d['model'] }}</td>
                            <td style="font-family: monospace;">{{ $d['imei'] }}</td>
                            <td>{{ $d['vehicle_plate'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($accItems))
            <h3 class="section">B. Daftar Aksesoris</h3>
            <table>
                <thead>
                    <tr>
                        <th class="center" style="width: 36px;">No</th>
                        <th>Kode</th>
                        <th>Nama Aksesoris</th>
                        <th class="center">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accItems as $i => $a)
                        <tr>
                            <td class="center">{{ $i + 1 }}</td>
                            <td style="font-family: monospace;">{{ $a['code'] }}</td>
                            <td>{{ $a['name'] }}</td>
                            <td class="center">{{ $a['qty'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="note">
            <strong>Catatan:</strong> Dengan menandatangani dokumen ini, penerima menyatakan telah menerima seluruh barang
            di atas dalam kondisi baik dan lengkap, serta bertanggung jawab penuh atas penggunaan dan pengembaliannya
            sesuai ketentuan yang berlaku.
        </div>

        <div class="signatures">
            <div class="sign-box">
                <div class="role">Yang Menyerahkan,</div>
                <div class="line">{{ $operator }}</div>
            </div>
            <div class="sign-box">
                <div class="role">Yang Menerima,</div>
                <div class="line">{{ $recipientName }}</div>
            </div>
        </div>
    </div>

    @if($autoprint)
    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 500));
    </script>
    @endif
</body>
</html>
