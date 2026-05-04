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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tipo')->default('exception');
            $table->string('mensagem');
            $table->string('arquivo')->nullable();
            $table->integer('linha')->nullable();
            $table->text('stack_trace')->nullable();
            $table->string('url')->nullable();
            $table->string('metodo')->nullable();
            $table->string('ip')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable(); // sem FK — banco separado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
