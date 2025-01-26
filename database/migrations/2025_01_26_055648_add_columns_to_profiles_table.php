<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\Profile;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 一時的にNULL許可でカラムを追加
        Schema::table('profiles', function (Blueprint $table) {
            $table->date('birthday')->nullable();
            $table->string('gender')->nullable();
            $table->text('introduction')->nullable();
        });

        // 既存レコードにデフォルト値を設定
        Profile::whereNull('birthday')->update([
            'birthday' => Carbon::create(2000, 1, 1),
            'gender' => 'not_specified'
        ]);

        // NULL制約を追加
        Schema::table('profiles', function (Blueprint $table) {
            $table->date('birthday')->nullable(false)->change();
            $table->string('gender')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'birthday',
                'gender',
                'introduction'
            ]);
        });
    }
};
