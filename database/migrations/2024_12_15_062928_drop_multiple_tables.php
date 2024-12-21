<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $mpForeignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND TABLE_NAME = 'marketplaces'
            AND CONSTRAINT_SCHEMA = DATABASE()
        ");

        if (!empty($mpForeignKeys)) {
            foreach ($mpForeignKeys as $mpForeignKey) {
                Schema::table('marketplaces', function (Blueprint $table) use ($mpForeignKey) {
                    $table->dropForeign($mpForeignKey->CONSTRAINT_NAME);
                });
            }
        }

        $whForeignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND TABLE_NAME = 'warehouses'
            AND CONSTRAINT_SCHEMA = DATABASE()
        ");

        if (!empty($whForeignKeys)) {
            foreach ($whForeignKeys as $whForeignKey) {
                Schema::table('warehouses', function (Blueprint $table) use ($whForeignKey) {
                    $table->dropForeign($whForeignKey->CONSTRAINT_NAME);
                });
            }
        }
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_filetypes');
        Schema::dropIfExists('item_origins');
        Schema::dropIfExists('item_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
