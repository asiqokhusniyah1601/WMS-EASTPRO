<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This migration was previously used to modify gsm_simcards for master-data purposes.
 * It has been reverted to a NO-OP because gsm_simcards must remain the physical stock table.
 * A separate `simcard_masters` table now handles master data (see next migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        // NO-OP: gsm_simcards table structure is managed by its original migration.
    }

    public function down(): void
    {
        // NO-OP
    }
};
