<?php
$file = 'app/Http/Controllers/PageController.php';
$content = file_get_contents($file);

$newMethod = <<<'EOD'
    public function exportCustomExcel(Request $request, ReportService $reports)
    {
        $filters = $reports->resolveFilters($request->only(['from', 'to', 'period', 'warehouse']));
        $whCode = $filters['warehouse'] ?? 'Semua Gudang';
        $whName = $whCode === 'Semua Gudang' ? 'Semua Gudang' : (Warehouse::where('code', $whCode)->value('name') ?? $whCode);
        
        $wantStok = $request->query('stok', '0') === '1';
        $wantMasuk = $request->query('masuk', '0') === '1';
        $wantKeluar = $request->query('keluar', '0') === '1';
        $wantTeknisi = $request->query('teknisi', '0') === '1';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFED7D31']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $borderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        $sheetIndex = 0;

        // 1. Laporan Stok Barang
        if ($wantStok) {
            $sc = $reports->stockCard($filters);
            $deviceRows = $sc['device']['rows'] ?? [];
            
            $deadStockList = $reports->aging($filters['warehouse'])['dead_stock'] ?? [];
            $deadStockByModel = collect($deadStockList)->groupBy('model')->map->count();

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle('Laporan Stok Barang');
            
            $headersLeft = ['Nama Barang', 'Satuan', 'Stok Awal', 'Barang Masuk', 'Barang Keluar', 'Sisa', 'Barang Beku', 'STOCK AKHIR'];
            foreach ($headersLeft as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
            }
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

            $row = 2;
            // Devices
            foreach ($deviceRows as $dr) {
                $name = $dr['name'];
                $awal = $dr['opening'];
                $masuk = $dr['in'];
                $keluar = $dr['out'];
                $sisa = $dr['closing'];
                $beku = $deadStockByModel[$name] ?? 0;
                $akhir = $sisa;

                $sheet->setCellValue('A'.$row, $name);
                $sheet->setCellValue('B'.$row, 'Pcs');
                $sheet->setCellValue('C'.$row, $awal);
                $sheet->setCellValue('D'.$row, $masuk);
                $sheet->setCellValue('E'.$row, $keluar);
                $sheet->setCellValue('F'.$row, $sisa);
                $sheet->setCellValue('G'.$row, $beku);
                $sheet->setCellValue('H'.$row, $akhir);
                $row++;
            }
            // ACC
            foreach ($sc['accessory']['rows'] ?? [] as $r) {
                $sheet->setCellValue('A'.$row, 'ACC: ' . $r['name']);
                $sheet->setCellValue('B'.$row, 'Pcs');
                $sheet->setCellValue('C'.$row, $r['opening']);
                $sheet->setCellValue('D'.$row, $r['in']);
                $sheet->setCellValue('E'.$row, $r['out']);
                $sheet->setCellValue('F'.$row, $r['closing']);
                $sheet->setCellValue('G'.$row, '-');
                $sheet->setCellValue('H'.$row, max(0, $r['closing']));
                $row++;
            }
            // GSM
            foreach ($sc['gsm']['rows'] ?? [] as $r) {
                $sheet->setCellValue('A'.$row, 'GSM: ' . $r['name']);
                $sheet->setCellValue('B'.$row, 'Pcs');
                $sheet->setCellValue('C'.$row, $r['opening']);
                $sheet->setCellValue('D'.$row, $r['in']);
                $sheet->setCellValue('E'.$row, $r['out']);
                $sheet->setCellValue('F'.$row, $r['closing']);
                $sheet->setCellValue('G'.$row, '-');
                $sheet->setCellValue('H'.$row, max(0, $r['closing']));
                $row++;
            }

            if ($row > 2) {
                $sheet->getStyle('A2:H'.($row - 1))->applyFromArray($borderStyle);
                for($r=2; $r<$row; $r++) {
                    if($r % 2 == 0) $sheet->getStyle('A'.$r.':H'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
                }
            }
            foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 2. Barang Masuk
        if ($wantMasuk) {
            $inTransactions = DeviceTransaction::with('device')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->whereIn('action', ReportService::IN_ACTIONS);
            if ($filters['warehouse']) {
                $inTransactions->where(function($q) use ($filters) {
                    $wh = $filters['warehouse'];
                    if (is_array($wh)) { $q->whereIn('to_location', $wh)->orWhereIn('from_location', $wh); } 
                    else { $q->where('to_location', $wh)->orWhere('from_location', $wh); }
                });
            }
            $inTransactions = $inTransactions->orderBy('created_at')->get();
            
            $accIn = \App\Models\AccessoryTransaction::with('accessory')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->where('action', 'IN');
            if ($filters['warehouse']) { $accIn->where('to_location', $filters['warehouse']); }
            $accIn = $accIn->get();

            $gsmIn = \App\Models\SimcardTransaction::whereBetween('created_at', [$filters['from'], $filters['to']])
                ->where('action', 'IN');
            if ($filters['warehouse']) { $gsmIn->where('to_location', $filters['warehouse']); }
            $gsmIn = $gsmIn->get();

            $summaryMasuk = [];
            foreach($inTransactions as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = $t->device->model ?? $t->device->type ?? '-';
                $name = $t->device->model ?? $t->device->type ?? 'Device Lain';
                $ket = $t->to_location ?: ($t->device->current_holder ?? '-');
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryMasuk[$key])) $summaryMasuk[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryMasuk[$key]['qty'] += 1;
            }
            foreach($accIn as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = $t->accessory_code;
                $name = 'ACC: '.($t->accessory->name ?? $t->accessory_code);
                $ket = $t->to_location ?? '-';
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryMasuk[$key])) $summaryMasuk[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryMasuk[$key]['qty'] += $t->qty;
            }
            foreach($gsmIn as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = 'GSM';
                $name = 'GSM / SIMCARD';
                $ket = $t->to_location ?? '-';
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryMasuk[$key])) $summaryMasuk[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryMasuk[$key]['qty'] += 1;
            }
            usort($summaryMasuk, function($a, $b) { return strtotime(str_replace('/','-',''.$b['date'])) <=> strtotime(str_replace('/','-',''.$a['date'])); });

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle('Barang Masuk');
            
            $headers = ['Tgl', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Satuan', 'Keterangan'];
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
            }
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($summaryMasuk as $data) {
                $sheet->setCellValue('A'.$row, $data['date']);
                $sheet->setCellValue('B'.$row, $data['code']);
                $sheet->setCellValue('C'.$row, $data['name']);
                $sheet->setCellValue('D'.$row, $data['qty']);
                $sheet->setCellValue('E'.$row, 'Pcs');
                $sheet->setCellValue('F'.$row, $data['ket']);
                $row++;
            }
            if ($row > 2) {
                $sheet->getStyle('A2:F'.($row - 1))->applyFromArray($borderStyle);
                for($r=2; $r<$row; $r++) {
                    if($r % 2 == 0) $sheet->getStyle('A'.$r.':F'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
                }
            }
            foreach (range('A', 'F') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 3. Barang Keluar
        if ($wantKeluar) {
            $outTransactions = DeviceTransaction::with('device')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->whereIn('action', ReportService::OUT_ACTIONS);
            if ($filters['warehouse']) {
                $outTransactions->where(function($q) use ($filters) {
                    $wh = $filters['warehouse'];
                    if (is_array($wh)) { $q->whereIn('to_location', $wh)->orWhereIn('from_location', $wh); } 
                    else { $q->where('to_location', $wh)->orWhere('from_location', $wh); }
                });
            }
            $outTransactions = $outTransactions->orderBy('created_at')->get();
            
            $accOut = \App\Models\AccessoryTransaction::with('accessory')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->where('action', 'OUT');
            if ($filters['warehouse']) { $accOut->where('to_location', $filters['warehouse']); }
            $accOut = $accOut->get();

            $gsmOut = \App\Models\SimcardTransaction::whereBetween('created_at', [$filters['from'], $filters['to']])
                ->where('action', 'OUT');
            if ($filters['warehouse']) { $gsmOut->where('to_location', $filters['warehouse']); }
            $gsmOut = $gsmOut->get();

            $summaryKeluar = [];
            foreach($outTransactions as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = $t->device->model ?? $t->device->type ?? '-';
                $name = $t->device->model ?? $t->device->type ?? 'Device Lain';
                $ket = $t->to_location ?: ($t->device->current_holder ?? '-');
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryKeluar[$key])) $summaryKeluar[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryKeluar[$key]['qty'] += 1;
            }
            foreach($accOut as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = $t->accessory_code;
                $name = 'ACC: '.($t->accessory->name ?? $t->accessory_code);
                $ket = $t->to_location ?? '-';
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryKeluar[$key])) $summaryKeluar[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryKeluar[$key]['qty'] += $t->qty;
            }
            foreach($gsmOut as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = 'GSM';
                $name = 'GSM / SIMCARD';
                $ket = $t->to_location ?? '-';
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryKeluar[$key])) $summaryKeluar[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryKeluar[$key]['qty'] += 1;
            }
            usort($summaryKeluar, function($a, $b) { return strtotime(str_replace('/','-',''.$b['date'])) <=> strtotime(str_replace('/','-',''.$a['date'])); });

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle('Barang Keluar');
            
            $headers = ['Tgl', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Satuan', 'Keterangan'];
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
            }
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($summaryKeluar as $data) {
                $sheet->setCellValue('A'.$row, $data['date']);
                $sheet->setCellValue('B'.$row, $data['code']);
                $sheet->setCellValue('C'.$row, $data['name']);
                $sheet->setCellValue('D'.$row, $data['qty']);
                $sheet->setCellValue('E'.$row, 'Pcs');
                $sheet->setCellValue('F'.$row, $data['ket']);
                $row++;
            }
            if ($row > 2) {
                $sheet->getStyle('A2:F'.($row - 1))->applyFromArray($borderStyle);
                for($r=2; $r<$row; $r++) {
                    if($r % 2 == 0) $sheet->getStyle('A'.$r.':F'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
                }
            }
            foreach (range('A', 'F') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 4. Stok Teknisi
        if ($wantTeknisi) {
            $whFilter = $filters['warehouse'];
            $techniciansList = \App\Models\User::where('role', 'teknisi')->get();

            $techStockMatrix = [];
            
            $rawTechDevices = Device::where('status', 'ISSUED')
                ->where('current_holder', 'like', 'Technician:%')
                ->when($whFilter, fn($q) => $q->where('warehouse_code', $whFilter))
                ->get(['current_holder', 'model']);

            foreach ($rawTechDevices as $d) {
                $model  = $d->model ?: 'Model Lain';
                $holder = trim(preg_replace('/^Technician:\s*/i', '', $d->current_holder));
                $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + 1;
            }

            $accIssued = \Illuminate\Support\Facades\DB::table('accessory_transactions')
                ->select('accessory_code', 'technician_code', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_issued'))
                ->where('action', 'OUT')
                ->where('to_location', 'like', 'Technician:%')
                ->when($whFilter, fn($q) => $q->where('from_location', $whFilter))
                ->groupBy('accessory_code', 'technician_code')
                ->get();

            foreach ($accIssued as $ai) {
                $holder = trim(preg_replace('/^Technician:\s*/i', '', $ai->technician_code));
                $model = 'ACC: ' . $ai->accessory_code;
                
                $returned = \Illuminate\Support\Facades\DB::table('accessory_transactions')
                    ->where('action', 'IN')
                    ->where('accessory_code', $ai->accessory_code)
                    ->where('technician_code', 'Technician: ' . $holder)
                    ->when($whFilter, fn($q) => $q->where('to_location', $whFilter))
                    ->sum('qty');
                    
                $current = $ai->total_issued - $returned;
                if ($current > 0) {
                    $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + $current;
                }
            }

            $gsmIssued = \Illuminate\Support\Facades\DB::table('simcard_transactions')
                ->select('msisdn', 'operator', 'to_location', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_issued'))
                ->where('action', 'OUT')
                ->where('to_location', 'like', 'Technician:%')
                ->when($whFilter, fn($q) => $q->where('from_location', $whFilter))
                ->groupBy('msisdn', 'operator', 'to_location')
                ->get();

            foreach ($gsmIssued as $gi) {
                $holder = trim(preg_replace('/^Technician:\s*/i', '', $gi->to_location));
                $model = 'GSM / SIMCARD';
                
                $returned = \Illuminate\Support\Facades\DB::table('simcard_transactions')
                    ->where('action', 'IN')
                    ->where('msisdn', $gi->msisdn)
                    ->where('from_location', 'Technician: ' . $holder)
                    ->when($whFilter, fn($q) => $q->where('to_location', $whFilter))
                    ->count();
                    
                $current = $gi->total_issued - $returned;
                if ($current > 0) {
                    $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + $current;
                }
            }

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle('Stok Teknisi');
            
            $sheet->setCellValue('A1', 'Nama Barang');
            $colIndex = 1; // B
            foreach ($techniciansList as $tech) {
                $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(++$colIndex);
                $sheet->setCellValue($colStr . '1', $tech->name);
            }
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($techStockMatrix as $modelName => $techs) {
                $sheet->setCellValue('A'.$row, $modelName);
                $c = 1;
                foreach ($techniciansList as $tech) {
                    $cStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(++$c);
                    $qty = $techs[$tech->name] ?? '';
                    $sheet->setCellValue($cStr.$row, $qty);
                }
                $row++;
            }
            
            if ($row > 2) {
                $sheet->getStyle('A2:'.$lastCol.($row - 1))->applyFromArray($borderStyle);
                for($r=2; $r<$row; $r++) {
                    if($r % 2 == 0) $sheet->getStyle('A'.$r.':'.$lastCol.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
                }
            }
            foreach (range(1, $colIndex) as $c) {
                $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
            }
        }

        if ($spreadsheet->getSheetCount() == 0) {
            $sheet = $spreadsheet->createSheet(0);
            $sheet->setTitle('Kosong');
            $sheet->setCellValue('A1', 'Tidak ada data dipilih');
        }

        $spreadsheet->setActiveSheetIndex(0);
        
        $parts = [];
        if ($wantStok) $parts[] = 'Stok';
        if ($wantMasuk) $parts[] = 'Masuk';
        if ($wantKeluar) $parts[] = 'Keluar';
        if ($wantTeknisi) $parts[] = 'Teknisi';
        $fileName = 'Export_' . implode('_', $parts) . '_' . date('Ymd_His') . '.xlsx';
        
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
EOD;

$pattern = '/public function exportCustomExcel\(.*?\n    \}\n/s';
$content = preg_replace($pattern, $newMethod . "\n", $content);
file_put_contents($file, $content);
echo "Replaced exportCustomExcel successfully.\n";
