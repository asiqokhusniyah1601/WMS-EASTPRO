<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        DB::table('app_settings')->insert([
            ['key' => 'app_logo', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_favicon', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_mode', 'value' => 'dark', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
