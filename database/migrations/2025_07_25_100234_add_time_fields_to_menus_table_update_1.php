<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMenuTimeToDateColumns extends Migration
{
    public function up()
    {
        Schema::table('menus', function (Blueprint $table) {
            // ลบ column เดิม
            $table->dropColumn(['start_time', 'end_time']);

            // เพิ่ม column ใหม่
            $table->date('start_date')->nullable()->after('base_price');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down()
    {
        Schema::table('menus', function (Blueprint $table) {
            // ย้อนกลับ: ลบ column ใหม่ แล้วคืน column เดิม
            $table->dropColumn(['start_date', 'end_date']);

            $table->time('start_time')->nullable()->after('base_price');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }
}
