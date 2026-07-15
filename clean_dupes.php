<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$duplicates = DB::select("
    SELECT device_sn, action, DATE(created_at) as dt, count(*) as cnt
    FROM device_transactions
    WHERE action IN ('RECEIVING', 'RETURN')
    GROUP BY device_sn, action, DATE(created_at)
    HAVING count(*) > 1
");

echo "=== Duplicate Transactions ===\n";
foreach($duplicates as $dup) {
    echo "SN: {$dup->device_sn} | Action: {$dup->action} | Date: {$dup->dt} | Count: {$dup->cnt}\n";
    
    // Get all transaction IDs for this duplicate set
    $txs = DB::table('device_transactions')
        ->where('device_sn', $dup->device_sn)
        ->where('action', $dup->action)
        ->whereDate('created_at', $dup->dt)
        ->orderBy('id', 'asc')
        ->pluck('id')
        ->toArray();
        
    // Keep the first one, delete the rest
    $keepId = array_shift($txs);
    echo "  Keeping ID: {$keepId}, Deleting IDs: " . implode(', ', $txs) . "\n";
    
    DB::table('device_transactions')->whereIn('id', $txs)->delete();
    echo "  Deleted.\n";
}

// Now also check if there are any devices with EXACT duplicate SNs in the devices table (ignoring case)
$duplicateDevices = DB::select("
    SELECT LOWER(serial_number) as sn_lower, count(*) as cnt
    FROM devices
    GROUP BY LOWER(serial_number)
    HAVING count(*) > 1
");

echo "\n=== Duplicate Devices (Case Insensitive) ===\n";
foreach($duplicateDevices as $dup) {
    echo "SN: {$dup->sn_lower} | Count: {$dup->cnt}\n";
    
    $devs = DB::table('devices')
        ->whereRaw('LOWER(serial_number) = ?', [$dup->sn_lower])
        ->orderBy('id', 'asc')
        ->pluck('id')
        ->toArray();
        
    $keepId = array_shift($devs);
    echo "  Keeping ID: {$keepId}, Deleting IDs: " . implode(', ', $devs) . "\n";
    DB::table('devices')->whereIn('id', $devs)->delete();
}

echo "\nDone.\n";
