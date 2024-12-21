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
        Schema::create('item_origins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('item_filetypes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->constrained('item_origins');
            $table->foreignId('itemtype_id')->constrained('item_types');
            $table->foreignId('filetype_id')->constrained('item_filetypes');
            $table->integer('totalsize');
            $table->string('filename');
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('nickname');
            $table->string('last_name');
            $table->string('fast_name');
            $table->string('user_thumbnail')->nullable();
            $table->string('main_room_view')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('item_id')->constrained('items');
            $table->string('name');
            $table->string('thumbnail');
            $table->integer('tags')->nullable();;
            $table->integer('views');
            $table->integer('price');
            $table->boolean('status');
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('item_id')->constrained('items');
            $table->string('name');
            $table->string('thumbnail');
            $table->boolean('favorite');
            $table->text('memo')->nullable();
            $table->timestamps();
        });

        Schema::create('mpcomments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('marketplace_id')->constrained('marketplaces');
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('mptags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('room_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('room_id')->constrained('rooms');
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('whtags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('marketplace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('marketplace_id')->constrained('marketplaces');
            $table->timestamps();
        });

        Schema::create('marketplace_mptag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained('marketplaces');
            $table->foreignId('mptag_id')->constrained('mptags');
            $table->timestamps();
        });

        Schema::create('room_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('room_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('warehouse_id')->constrained('warehouses');
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

        Schema::create('warehouse_whtag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whtag_id')->constrained('whtags');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_whtag');
        Schema::dropIfExists('room_warehouse');
        Schema::dropIfExists('room_user');
        Schema::dropIfExists('marketplace_mptag');
        Schema::dropIfExists('marketplace_user');
        Schema::dropIfExists('whtags');
        Schema::dropIfExists('room_comments');
        Schema::dropIfExists('mptags');
        Schema::dropIfExists('mpcomments');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('marketplaces');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_filetypes');
        Schema::dropIfExists('item_types');
        Schema::dropIfExists('item_origins');
    }
};
