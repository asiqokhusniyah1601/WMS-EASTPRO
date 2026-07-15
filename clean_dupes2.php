<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$duplicates = DB::select("
    SELECT device_sn, action, count(*) as cnt
    FROM device_transactions
    WHERE action IN ('RECEIVING', 'RETURN', 'ISSUED', 'TRANSFER_OUT', 'TRANSFER_IN', 'QC_PASS', 'QC_FAIL')
    GROUP BY device_sn, action
    HAVING count(*) > 1
");

echo "=== Duplicate Transactions (Regardless of Date) ===\n";
foreach($duplicates as $dup) {
    echo "SN: {$dup->device_sn} | Action: {$dup->action} | Count: {$dup->cnt}\n";
    
    // Get all transaction IDs for this duplicate set
    $txs = DB::table('device_transactions')
        ->where('device_sn', $dup->device_sn)
        ->where('action', $dup->action)
        ->orderBy('id', 'asc')
        ->pluck('id')
        ->toArray();
        
    // Keep the first one, delete the rest
    $keepId = array_shift($txs);
    echo "  Keeping ID: {$keepId}, Deleting IDs: " . implode(', ', $txs) . "\n";
    
    DB::table('device_transactions')->whereIn('id', $txs)->delete();
    echo "  Deleted.\n";
}

echo "\nDone.\n";
