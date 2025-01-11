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
        Schema::create('item_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('item_id')->constrained('items');
            $table->float('position_x');
            $table->float('position_y');
            $table->float('position_z');
            $table->float('rotation_x');
            $table->float('rotation_y');
            $table->float('rotation_z');
            $table->float('rotation_w');
            $table->float('scale_x');
            $table->float('scale_y');
            $table->float('scale_z');
            $table->float('parentindex');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_room');
    }
};
