<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('route_name')->nullable();
            $table->string('method');
            $table->string('url');
            $table->json('parameters')->nullable();
            $table->integer('status_code')->nullable();  // ステータスコードを追加
            $table->text('error_response')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_logs');
    }
};
