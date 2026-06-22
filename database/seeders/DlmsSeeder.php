<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use App\Models\Technician;
use App\Models\Accessory;
use App\Models\Device;
use App\Models\DeviceTransaction;
use App\Models\DeliveryOrder;

use App\Models\GsmSimcard;

class DlmsSeeder extends Seeder
{
    public function run(): void
    {
        // 0. GSM SIM Cards
        $sims = [
            ['msisdn' => '6281122334455', 'provider' => 'Telkomsel', 'category' => 'Telkomsel Halo', 'status' => 'IN_STOCK'],
            ['msisdn' => '6281223344556', 'provider' => 'Indosat Ooredoo', 'category' => 'B2B', 'status' => 'IN_STOCK'],
            ['msisdn' => '6281323344557', 'provider' => 'XL Axiata', 'category' => 'XL Biz', 'status' => 'IN_STOCK'],
        ];

        foreach ($sims as $sim) {
            GsmSimcard::updateOrCreate(['msisdn' => $sim['msisdn']], $sim);
        }

        // 1. Warehouses
        $warehouses = [
            ['code' => 'WH-PUSAT', 'name' => 'Warehouse Pusat (Central Warehouse)'],
            ['code' => 'WH-REG-WEST', 'name' => 'Regional Warehouse West'],
            ['code' => 'WH-REG-EAST', 'name' => 'Regional Warehouse East'],
            ['code' => 'WH-AREA-SUB', 'name' => 'Area Warehouse Surabaya / Hub'],
            ['code' => 'WH-AREA-SDA', 'name' => 'Area Warehouse Sidoarjo / Hub'],
            ['code' => 'WH-AREA-MLG', 'name' => 'Area Warehouse Malang / Hub'],
        ];

        foreach ($warehouses as $wh) {
            Warehouse::updateOrCreate(['code' => $wh['code']], $wh);
        }

        // 2. Technicians
        $technicians = [
            ['code' => 'TECH-001', 'name' => 'Budi Santoso'],
            ['code' => 'TECH-002', 'name' => 'John Doe'],
            ['code' => 'TECH-003', 'name' => 'Jane Smith'],
            ['code' => 'TECH-004', 'name' => 'Ahmad Rian'],
        ];

        foreach ($technicians as $tech) {
            Technician::updateOrCreate(['code' => $tech['code']], $tech);
        }

        // 3. Accessories
        $accessories = [
            ['code' => 'ACC-CABLE', 'name' => 'Power Cable harness', 'qty' => 150],
            ['code' => 'ACC-ANTENNA', 'name' => 'GPS External Antenna', 'qty' => 85],
            ['code' => 'ACC-MOUNT', 'name' => 'Dashcam Windshield Mount', 'qty' => 60],
        ];

        foreach ($accessories as $acc) {
            Accessory::updateOrCreate(['code' => $acc['code']], $acc);
        }

        // 4. Devices
        $dev1 = Device::updateOrCreate(
            ['serial_number' => 'GPS-982173812'],
            [
                'imei' => '358291039821738',
                'type' => 'GPS Tracker',
                'model' => 'SuperSpring VT-90E',
                'status' => 'IN_STOCK',
                'warehouse_code' => 'WH-PUSAT',
                'current_holder' => 'Warehouse Pusat',
            ]
        );

        $dev2 = Device::updateOrCreate(
            ['serial_number' => 'MDVR-88291'],
            [
                'imei' => '351122334455667',
                'type' => 'MDVR',
                'model' => 'Hikvision 4-CH Mobile DVR',
                'status' => 'IN_STOCK',
                'warehouse_code' => 'WH-PUSAT',
                'current_holder' => 'Warehouse Pusat',
            ]
        );

        $dev3 = Device::updateOrCreate(
            ['serial_number' => 'CAM-772910'],
            [
                'imei' => '352233445566778',
                'type' => 'Dashcam',
                'model' => 'BlackVue DR900X',
                'status' => 'INSTALLED',
                'warehouse_code' => 'WH-AREA-SUB',
                'current_holder' => 'Plat B 1234 SK',
            ]
        );

        // 5. Device Transactions (Logs)
        DeviceTransaction::updateOrCreate(
            ['device_sn' => 'GPS-982173812', 'action' => 'RECEIVING'],
            [
                'device_id' => $dev1->id,
                'from_location' => 'Supplier',
                'to_location' => 'WH-PUSAT',
                'operator' => 'Super Admin',
                'scanned_by' => 'Scanner-HID-01',
                'via_web' => true,
                'created_at' => now()->subDays(2),
            ]
        );

        DeviceTransaction::updateOrCreate(
            ['device_sn' => 'MDVR-88291', 'action' => 'RECEIVING'],
            [
                'device_id' => $dev2->id,
                'from_location' => 'Supplier',
                'to_location' => 'WH-PUSAT',
                'operator' => 'Super Admin',
                'scanned_by' => 'Scanner-HID-01',
                'via_web' => true,
                'created_at' => now()->subDays(1),
            ]
        );

        // 6. Delivery Order
        $do = DeliveryOrder::updateOrCreate(
            ['id' => 'SJ-260619-01'],
            [
                'from_warehouse_code' => 'WH-PUSAT',
                'to_warehouse_code' => 'WH-AREA-SUB',
                'status' => 'IN_TRANSIT',
            ]
        );

        // Attach device to DO
        $do->devices()->syncWithoutDetaching([$dev1->id]);
    }
}
