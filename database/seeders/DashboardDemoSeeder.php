<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use App\Models\Technician;
use App\Models\Accessory;
use App\Models\WarehouseAccessory;
use App\Models\Device;
use App\Models\DeviceTransaction;
use App\Models\StockAlertThreshold;
use Carbon\Carbon;

/**
 * Realistic demo dataset so the Dashboard (metrics, burn-rate chart,
 * distribution donut, and AI insights) renders with meaningful data.
 *
 * Idempotent: re-running refreshes the DEMO-* devices and their logs.
 *
 *   php artisan db:seed --class=DashboardDemoSeeder
 */
class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Wipe previous demo transactions so burn-rate logs stay idempotent.
        DeviceTransaction::where('device_sn', 'like', 'DEMO-%')->delete();

        // --- Master data (idempotent) ---
        $warehouses = [
            ['code' => 'WH-PUSAT',    'name' => 'Warehouse Pusat (Central Warehouse)'],
            ['code' => 'WH-REG-WEST', 'name' => 'Regional Warehouse West'],
            ['code' => 'WH-REG-EAST', 'name' => 'Regional Warehouse East'],
            ['code' => 'WH-AREA-SUB', 'name' => 'Area Warehouse Surabaya / Hub'],
            ['code' => 'WH-AREA-SDA', 'name' => 'Area Warehouse Sidoarjo / Hub'],
            ['code' => 'WH-AREA-MLG', 'name' => 'Area Warehouse Malang / Hub'],
        ];
        foreach ($warehouses as $wh) {
            Warehouse::updateOrCreate(['code' => $wh['code']], $wh);
        }

        $technicians = [
            ['code' => 'TECH-001', 'name' => 'Budi Santoso'],
            ['code' => 'TECH-002', 'name' => 'John Doe'],
            ['code' => 'TECH-003', 'name' => 'Jane Smith'],
            ['code' => 'TECH-004', 'name' => 'Ahmad Rian'],
        ];
        foreach ($technicians as $t) {
            Technician::updateOrCreate(['code' => $t['code']], $t);
        }

        $accessories = [
            ['code' => 'ACC-CABLE',   'name' => 'Power Cable Harness',         'qty' => 150],
            ['code' => 'ACC-ANTENNA', 'name' => 'GPS External Antenna',        'qty' => 85],
            ['code' => 'ACC-MOUNT',   'name' => 'Dashcam Windshield Mount',    'qty' => 60],
        ];
        foreach ($accessories as $a) {
            Accessory::updateOrCreate(['code' => $a['code']], $a);
        }

        // --- 20 realistic devices across statuses & warehouses ---
        // [serial, imei, type, model, status, warehouse, holder, issuedDaysAgo]
        $devices = [
            // IN_STOCK (9) -> spread across warehouses for a multi-slice donut
            ['DEMO-GPS-0001', '350000000000001', 'GPS Tracker', 'FMC130',         'IN_STOCK',   'WH-PUSAT',    'Warehouse Pusat',            null],
            ['DEMO-GPS-0002', '350000000000002', 'GPS Tracker', 'FMC920',         'IN_STOCK',   'WH-PUSAT',    'Warehouse Pusat',            null],
            ['DEMO-GPS-0003', '350000000000003', 'GPS Tracker', 'Trace5',         'IN_STOCK',   'WH-REG-WEST', 'Regional Warehouse West',    null],
            ['DEMO-GPS-0004', '350000000000004', 'GPS Tracker', 'GT06N',          'IN_STOCK',   'WH-REG-EAST', 'Regional Warehouse East',    null],
            ['DEMO-MDR-0005', '350000000000005', 'MDVR',        'Hero-ME41-04',   'IN_STOCK',   'WH-AREA-SUB', 'Area Warehouse Surabaya',    null],
            ['DEMO-MDR-0006', '350000000000006', 'MDVR',        'X3-H04',         'IN_STOCK',   'WH-PUSAT',    'Warehouse Pusat',            null],
            ['DEMO-CAM-0007', '350000000000007', 'Dashcam',     'JC400',          'IN_STOCK',   'WH-AREA-MLG', 'Area Warehouse Malang',      null],
            ['DEMO-GPS-0008', '350000000000008', 'GPS Tracker', 'FMB120',         'IN_STOCK',   'WH-AREA-SDA', 'Area Warehouse Sidoarjo',    null],
            ['DEMO-GPS-0009', '350000000000009', 'GPS Tracker', 'HCV5',           'IN_STOCK',   'WH-REG-WEST', 'Regional Warehouse West',    null],

            // ISSUED (5) -> 2 are "dead stock" (> 7 days), 3 recent
            ['DEMO-GPS-0010', '350000000000010', 'GPS Tracker', 'FMC130',         'ISSUED',     'WH-PUSAT',    'Technician: Budi Santoso',   12],
            ['DEMO-GPS-0011', '350000000000011', 'GPS Tracker', 'GT06N',          'ISSUED',     'WH-REG-WEST', 'Technician: John Doe',       9],
            ['DEMO-MDR-0012', '350000000000012', 'MDVR',        'X3-H04',         'ISSUED',     'WH-AREA-SUB', 'Technician: Jane Smith',     3],
            ['DEMO-CAM-0013', '350000000000013', 'Dashcam',     'JC400',          'ISSUED',     'WH-AREA-MLG', 'Technician: Ahmad Rian',     2],
            ['DEMO-GPS-0014', '350000000000014', 'GPS Tracker', 'FMC920',         'ISSUED',     'WH-PUSAT',    'Technician: Budi Santoso',   5],

            // INSTALLED (3)
            ['DEMO-GPS-0015', '350000000000015', 'GPS Tracker', 'FMC130',         'INSTALLED',  'WH-PUSAT',    'Plat L 1234 AB',             20],
            ['DEMO-MDR-0016', '350000000000016', 'MDVR',        'Hero-ME41-04',   'INSTALLED',  'WH-AREA-SUB', 'Plat W 5678 CD',             18],
            ['DEMO-CAM-0017', '350000000000017', 'Dashcam',     'JC400',          'INSTALLED',  'WH-AREA-MLG', 'Plat N 9012 EF',             15],

            // IN_TRANSIT (2)
            ['DEMO-GPS-0018', '350000000000018', 'GPS Tracker', 'FMB120',         'IN_TRANSIT', 'WH-REG-EAST', 'In Transit to WH-AREA-MLG',  6],
            ['DEMO-GPS-0019', '350000000000019', 'GPS Tracker', 'Trace5',         'IN_TRANSIT', 'WH-PUSAT',    'In Transit to WH-REG-WEST',  4],

            // RETURNED (1)
            ['DEMO-MDR-0020', '350000000000020', 'MDVR',        'X3-H04',         'RETURNED',   'WH-AREA-SUB', 'Warehouse WH-AREA-SUB',      8],
        ];

        $createdDevices = [];
        foreach ($devices as $d) {
            [$sn, $imei, $type, $model, $status, $wh, $holder, $issuedDaysAgo] = $d;

            $device = Device::updateOrCreate(
                ['serial_number' => $sn],
                [
                    'imei'           => $imei,
                    'type'           => $type,
                    'model'          => $model,
                    'status'         => $status,
                    'warehouse_code' => $wh,
                    'current_holder' => $holder,
                ]
            );
            $createdDevices[$sn] = $device;

            // RECEIVING log ~ a few days before it left stock (or recently for in-stock).
            $receivedDaysAgo = $issuedDaysAgo !== null ? $issuedDaysAgo + 3 : rand(1, 25);
            $this->log($device, 'RECEIVING', 'Supplier', $wh, Carbon::now()->subDays($receivedDaysAgo));

            // For devices that left stock, log the leaving event on its issued day
            // and pin the device's updated_at so dead-stock detection works.
            if ($issuedDaysAgo !== null) {
                $action = in_array($status, ['IN_TRANSIT']) ? 'TRANSFER_OUT' : 'ISSUED';
                $this->log($device, $action, $wh, $holder, Carbon::now()->subDays($issuedDaysAgo));

                Device::where('id', $device->id)
                    ->update(['updated_at' => Carbon::now()->subDays($issuedDaysAgo)]);
            }
        }

        // --- Extra burn-rate history so the 30-day line chart shows a real trend ---
        // (devices are issued/transferred over time; counts vary per day)
        $burnHistory = [
            ['DEMO-GPS-0001', 'ISSUED', 28], ['DEMO-GPS-0002', 'ISSUED', 26],
            ['DEMO-GPS-0003', 'TRANSFER_OUT', 24], ['DEMO-MDR-0005', 'ISSUED', 22],
            ['DEMO-GPS-0004', 'ISSUED', 21], ['DEMO-CAM-0007', 'ISSUED', 19],
            ['DEMO-GPS-0008', 'TRANSFER_OUT', 17], ['DEMO-MDR-0006', 'ISSUED', 14],
            ['DEMO-GPS-0009', 'ISSUED', 13], ['DEMO-GPS-0001', 'ISSUED', 11],
            ['DEMO-GPS-0002', 'TRANSFER_OUT', 10], ['DEMO-CAM-0007', 'ISSUED', 7],
            ['DEMO-MDR-0005', 'ISSUED', 6], ['DEMO-GPS-0003', 'ISSUED', 1],
            ['DEMO-GPS-0009', 'ISSUED', 0],
        ];
        foreach ($burnHistory as [$sn, $action, $daysAgo]) {
            $device = $createdDevices[$sn] ?? null;
            if (!$device) {
                continue;
            }
            $this->log($device, $action, $device->warehouse_code, 'Field Operation', Carbon::now()->subDays($daysAgo));
        }

        // --- Per-warehouse accessory stock (for low-stock ACCESSORY alerts) ---
        WarehouseAccessory::updateOrCreate(
            ['warehouse_code' => 'WH-PUSAT', 'accessory_code' => 'ACC-ANTENNA'],
            ['qty' => 2]
        );
        WarehouseAccessory::updateOrCreate(
            ['warehouse_code' => 'WH-REG-WEST', 'accessory_code' => 'ACC-CABLE'],
            ['qty' => 40]
        );

        // --- Stock alert thresholds (drive the AI insights panel) ---
        $thresholds = [
            // DEVICE thresholds
            ['WH-REG-EAST', 'DEVICE',    'GT06N',       3], // stock 1 -> warning
            ['WH-AREA-MLG', 'DEVICE',    'FMC130',      2], // stock 0 -> critical
            // ACCESSORY thresholds
            ['WH-PUSAT',    'ACCESSORY', 'ACC-ANTENNA', 5], // qty 2 -> warning
            ['WH-REG-WEST', 'ACCESSORY', 'ACC-MOUNT',   5], // no stock row -> critical
        ];
        foreach ($thresholds as [$wh, $itemType, $identifier, $min]) {
            StockAlertThreshold::updateOrCreate(
                ['warehouse_code' => $wh, 'item_type' => $itemType, 'item_identifier' => $identifier],
                ['min_stock_level' => $min]
            );
        }
    }

    /**
     * Insert a DeviceTransaction with an explicit timestamp.
     * created_at/updated_at are set directly (not via mass assignment) so
     * Eloquent does not override them with the current time.
     */
    private function log(Device $device, string $action, string $from, string $to, Carbon $at): void
    {
        $tx = new DeviceTransaction();
        $tx->fill([
            'device_id'     => $device->id,
            'device_sn'     => $device->serial_number,
            'action'        => $action,
            'from_location' => $from,
            'to_location'   => $to,
            'operator'      => 'Super Admin',
            'scanned_by'    => 'Scanner-HID-01',
            'via_web'       => true,
        ]);
        $tx->created_at = $at;
        $tx->updated_at = $at;
        $tx->save();
    }
}
