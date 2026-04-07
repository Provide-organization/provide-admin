<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('container_name', 150)->nullable();
            $table->string('db_name', 100)->nullable();
            $table->string('db_username', 100)->nullable();
            $table->enum('status', ['provisioning', 'active', 'failed', 'inactive'])->default('provisioning');
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_instances');
    }
};
