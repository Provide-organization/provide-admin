<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_instances', function (Blueprint $table) {
            $table->string('db_logs_name')->nullable()->after('db_name');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_instances', function (Blueprint $table) {
            $table->dropColumn('db_logs_name');
        });
    }
};
