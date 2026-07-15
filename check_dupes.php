<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$total  = DB::table('devices')->count();
$unique = DB::table('devices')->distinct()->count('serial_number');
echo "Total rows: {$total}\n";
echo "Unique SN: {$unique}\n";
echo "Possible duplicates: " . ($total - $unique) . "\n";
