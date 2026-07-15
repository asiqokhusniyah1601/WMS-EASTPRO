
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanda Terima {{ $receiptNo }}</title>
    <style>
        body { font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 13px; margin: 0; padding: 20px; background: #fff; color: #0f172a; line-height: 1.5; }
        .sheet { max-width: 850px; margin: 0 auto; padding: 20px; }
        
        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 5px; }
        .header-logo { flex: 1; }
        .header-logo img { width: 250px; height: auto; display: block; } /* UBAH ANGKA 250px INI UNTUK MEMBESARKAN/MENGECILKAN LOGO */
        
        .header-info { text-align: right; font-size: 11px; color: #334155; line-height: 1.4; }
        .header-info h2 { font-size: 16px; margin: 0 0 4px; font-weight: bold; color: #0f172a; }
        
        .divider { border-bottom: 2px solid #0f172a; margin-bottom: 25px; }
        
        .title { text-align: center; margin-bottom: 25px; }
        .title h3 { margin: 0; font-size: 16px; font-weight: bold; text-decoration: underline; letter-spacing: 0.5px; }
        
        .doc-no { text-align: center; font-weight: 600; margin-top: -20px; margin-bottom: 30px; font-size: 13px; color: #475569; }

        .meta-container { display: flex; gap: 20px; margin-bottom: 30px; }
        .meta-card { flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
        .meta-title { font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 12px; letter-spacing: 0.5px; text-transform: uppercase; }
        
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 4px 0; vertical-align: top; }
        .meta-table td.label { width: 110px; color: #64748b; font-weight: 400; }
        .meta-table td.val { font-weight: 500; color: #0f172a; }
        
        h4.section-title { font-size: 13px; font-weight: bold; margin: 25px 0 10px; color: #0f172a; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 12px; }
        .data-table th, .data-table td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        .data-table th { font-weight: 600; text-align: center; background-color: #f8fafc; color: #334155; }
        .data-table td.center { text-align: center; }
        
        .note { margin-top: 15px; line-height: 1.6; text-align: justify; font-size: 12px; color: #475569; }
        .signatures { width: 100%; margin-top: 60px; text-align: center; font-size: 13px; }
        .signatures td { width: 50%; vertical-align: top; padding-bottom: 90px; color: #334155; }
        .signatures .name { font-weight: bold; color: #0f172a; text-decoration: underline; }
        
        .footer { text-align: right; margin-top: 50px; font-size: 10px; color: #94a3b8; }
        
        @page {
            margin: 0; /* Menghapus header/footer default browser */
            size: auto;
        }
        
        @media print {
            body { padding: 0; }
            .sheet { padding: 0 15mm; }
            .no-print { display: none; }
            .meta-card { page-break-inside: avoid; }
            .footer { position: fixed; bottom: 5mm; right: 15mm; margin-top: 0; }
        }
        .page-margin-table { width: 100%; border: none; border-collapse: collapse; }
        .page-margin-table > thead > tr > td,
        .page-margin-table > tbody > tr > td,
        .page-margin-table > tfoot > tr > td { padding: 0; border: none; }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-weight: 600;">Cetak Dokumen</button>
        <a href="{{ route('dashboard') }}" style="margin-left: 10px; text-decoration: none; color: #2563eb; font-weight: 500;">Kembali ke Dashboard</a>
    </div>
    
    <table class="page-margin-table">
        <thead>
            <tr><td><div style="height: 15mm;"></div></td></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="sheet">
                        <div class="header">
            <div class="header-logo">
                <img src="{{ asset('img/easygo-new-logo.png') }}" alt="EasyGo Logo" onerror="this.style.display='none'">
            </div>
            <div class="header-info">
                <h2>PT. EASYGO INDONESIA</h2>
                Alamat : Jl. Parang Tritis Raya Komplek Indo Ruko Lodan No 1 AB<br>
                Rt 4 Rw 2 Ancol Kelurahan Pademangan, Jakarta Utara Kodepos : 14430<br>
                Phone : 021 698 30038 Fax : 021 451 4534 Email : cseasygo@easygo.co.id
            </div>
        </div>
        <div class="divider"></div>
        
        <div class="title">
            <h3>TANDA TERIMA</h3>
        </div>
        <div class="doc-no">
            No. Dokumen: {{ $receiptNo }}
        </div>
        
        <div class="meta-container">
            <div class="meta-card">
                <div class="meta-title">Diserahkan Kepada ({{ strtoupper($recipientType) }})</div>
                <table class="meta-table">
                    <tr>
                        <td class="label">Nama</td>
                        <td class="val">{{ $recipientName }}</td>
                    </tr>
                    @foreach($recipientMeta as $k => $v)
                    <tr>
                        <td class="label">{{ $k }}</td>
                        <td class="val">{{ $v }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            <div class="meta-card">
                <div class="meta-title">Informasi Penyerahan</div>
                <table class="meta-table">
                    <tr>
                        <td class="label">Tanggal</td>
                        <td class="val">{{ $issued->translatedFormat('d F Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Jam</td>
                        <td class="val">{{ $issued->format('H:i') }} WIB</td>
                    </tr>
                    <tr>
                        <td class="label">Gudang Asal</td>
                        <td class="val">{{ $warehouseName }}</td>
                    </tr>
                    <tr>
                        <td class="label">Petugas</td>
                        <td class="val">{{ $operator }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @php $sectionAlpha = 'A'; @endphp

        @if(!empty($deviceItems))
        @php
            $totalDevice = array_sum(array_column($deviceItems, 'qty'));
        @endphp
        <h4 class="section-title">{{ $sectionAlpha++ }}. Daftar Perangkat ({{ $totalDevice }} unit)</h4>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th>Serial Number</th>
                    <th style="width: 120px;">Tipe</th>
                    <th style="width: 120px;">Model</th>
                    <th style="width: 60px;">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deviceItems as $i => $d)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td style="line-height: 1.8;">{!! $d['serial_number'] !!}</td>
                    <td class="center">{{ $d['type'] }}</td>
                    <td class="center">{{ $d['model'] }}</td>
                    <td class="center" style="font-weight: 600;">{{ $d['qty'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(!empty($simItems))
        @php
            $totalSim = array_sum(array_column($simItems, 'qty'));
        @endphp
        <h4 class="section-title">{{ $sectionAlpha++ }}. Daftar Kartu GSM ({{ $totalSim }} unit)</h4>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th>MSISDN</th>
                    <th style="width: 120px;">Provider</th>
                    <th style="width: 120px;">Kategori</th>
                    <th style="width: 60px;">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($simItems as $i => $s)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td style="line-height: 1.8;">{!! $s['msisdn'] !!}</td>
                    <td class="center">{{ $s['provider'] }}</td>
                    <td class="center">{{ $s['category'] }}</td>
                    <td class="center" style="font-weight: 600;">{{ $s['qty'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(!empty($accItems))
        @php
            $totalAcc = array_sum(array_column($accItems, 'qty'));
        @endphp
        <h4 class="section-title">{{ $sectionAlpha++ }}. Daftar Aksesoris ({{ $totalAcc }} unit)</h4>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th>Kode</th>
                    <th>Nama Aksesoris</th>
                    <th style="width: 60px;">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accItems as $i => $a)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td class="center">{{ $a['code'] }}</td>
                    <td>{{ $a['name'] }}</td>
                    <td class="center" style="font-weight: 600;">{{ $a['qty'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <div style="page-break-inside: avoid; margin-bottom: 30px;">
            <div class="note">
                <b>Catatan:</b> Dengan menandatangani dokumen ini, penerima menyatakan telah menerima seluruh barang di atas 
                dalam kondisi baik dan lengkap, serta bertanggung jawab penuh atas penggunaan dan pengembaliannya 
                sesuai ketentuan yang berlaku.
            </div>

            <table class="signatures">
                <tr>
                    <td>Yang Menyerahkan,</td>
                    <td>Yang Menerima,</td>
                </tr>
                <tr>
                    <td class="name">{{ $operator }}</td>
                    <td class="name">{{ $recipientName }}</td>
                </tr>
            </table>
        </div>

                    </div>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr><td><div style="height: 15mm;"></div></td></tr>
        </tfoot>
    </table>

    <div class="footer">
        Powered by WMS EastPRO
    </div>
    
    @if(isset($autoprint) && $autoprint)
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
    @endif
</body>
</html>
