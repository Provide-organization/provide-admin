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
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable(); // sem FK — banco separado
            $table->string('metodo');
            $table->string('url');
            $table->string('rota')->nullable();
            $table->jsonb('payload')->nullable();
            $table->jsonb('resposta')->nullable();
            $table->integer('status_code');
            $table->integer('duracao_ms')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};
