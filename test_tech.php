<?php
$file = 'resources/views/reports.blade.php';
$content = file_get_contents($file);

$modal = "\n{{-- Modal CSV Download --}}\n<div id=\"csvModal\" style=\"display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;\">\n    <div style=\"background:var(--bg-primary); border-radius:14px; padding:28px 32px; min-width:340px; box-shadow:0 8px 40px rgba(0,0,0,.3); position:relative;\">\n        <h4 style=\"margin:0 0 18px; font-size:16px; font-weight:700;\"><i class=\"fa-solid fa-file-csv\" style=\"color:#3b82f6;\"></i> Pilih Data yang Ingin Diunduh (CSV)</h4>\n        <div style=\"display:flex; flex-direction:column; gap:12px; margin-bottom:20px;\">\n            <label style=\"display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;\">\n                <input type=\"checkbox\" id=\"csvChkMasuk\" checked style=\"width:16px;height:16px;\"> <span>Barang Masuk</span>\n            </label>\n            <label style=\"display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;\">\n                <input type=\"checkbox\" id=\"csvChkKeluar\" checked style=\"width:16px;height:16px;\"> <span>Barang Keluar</span>\n            </label>\n        </div>\n        <div style=\"display:flex; gap:10px; justify-content:flex-end;\">\n            <button class=\"btn btn-outline\" onclick=\"document.getElementById('csvModal').style.display='none'\">Batal</button>\n            <button class=\"btn btn-primary\" onclick=\"downloadCsv()\"><i class=\"fa-solid fa-download\"></i> Download</button>\n        </div>\n    </div>\n</div>\n";

// Insert modal before @endsection (content section)
$content = str_replace("@endsection\n\n@section('scripts')", $modal . "@endsection\n\n@section('scripts')", $content);

file_put_contents($file, $content);
echo "Done\n";
