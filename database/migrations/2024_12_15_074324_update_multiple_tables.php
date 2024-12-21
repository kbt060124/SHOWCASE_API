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
        Schema::table('profiles', function (Blueprint $table) {
            $table->renameColumn('fast_name', 'first_name');
            $table->string('attribute')->after('main_room_view')->nullable();
        });

        Schema::table('marketplaces', function (Blueprint $table) {
            $table->dropColumn(['item_id', 'tags']);
            $table->integer('totalsize')->nullable();
            $table->string('filename');
            $table->boolean('status')->change();
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('item_id');
            $table->boolean('favorite')->nullable()->change();
            $table->string('background_color')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
