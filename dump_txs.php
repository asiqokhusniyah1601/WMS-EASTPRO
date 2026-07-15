<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$txs = DB::table('device_transactions')
    ->whereIn('device_sn', ['240078773111', '240078755167'])
    ->get();
    
foreach ($txs as $t) {
    echo "ID: {$t->id} | SN: {$t->device_sn} | Action: {$t->action} | Date: {$t->created_at} | From: {$t->from_location} | To: {$t->to_location}\n";
}
