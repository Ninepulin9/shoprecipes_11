<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'manager', 'cashier', 'staff', 'user') DEFAULT 'user'");
        DB::table('users')->where('role', 'admin')->update(['role' => 'owner']);
        
        Schema::table('users', function (Blueprint $table) {
            // เพิ่มคอลัมน์สำหรับเก็บข้อมูลเพิ่มเติม
            $table->timestamp('last_login')->nullable()->after('remember_token');
            $table->boolean('is_active')->default(true)->after('last_login');
            $table->string('employee_id')->nullable()->after('is_active'); // รหัสพนักงาน
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login', 'is_active', 'employee_id']);
        });
        
        DB::table('users')->where('role', 'owner')->update(['role' => 'admin']);
        
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user') DEFAULT 'user'");
    }
};