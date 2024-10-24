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
        Schema::dropIfExists('mp_objects');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('shelves');
        Schema::dropIfExists('buckets');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 注意: この操作は元に戻せません
    }
};
