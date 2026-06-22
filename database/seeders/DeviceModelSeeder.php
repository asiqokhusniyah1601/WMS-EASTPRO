<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeviceModel;

class DeviceModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            // Teltonika
            ['brand' => 'Teltonika', 'type' => 'GPS Tracker', 'model' => 'FMC130'],
            ['brand' => 'Teltonika', 'type' => 'GPS Tracker', 'model' => 'FMC920'],
            ['brand' => 'Teltonika', 'type' => 'GPS Tracker', 'model' => 'FMB120'],
            
            // Ruptela
            ['brand' => 'Ruptela', 'type' => 'GPS Tracker', 'model' => 'Trace5'],
            ['brand' => 'Ruptela', 'type' => 'GPS Tracker', 'model' => 'HCV5'],
            
            // Concox / Jimi
            ['brand' => 'Concox', 'type' => 'GPS Tracker', 'model' => 'GT06N'],
            ['brand' => 'Concox', 'type' => 'Dashcam', 'model' => 'JC400'],
            
            // Others / MDVR
            ['brand' => 'Howen', 'type' => 'MDVR', 'model' => 'Hero-ME41-04'],
            ['brand' => 'Streamax', 'type' => 'MDVR', 'model' => 'X3-H04'],
        ];

        foreach ($models as $m) {
            DeviceModel::updateOrCreate(
                ['model' => $m['model']], // Model names are unique
                $m
            );
        }
    }
}
