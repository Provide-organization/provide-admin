<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo', 255);
            $table->string('cpf', 14)->unique()->nullable();
            $table->string('telefone', 20)->nullable();
            $table->foreignId('usuario_id')->nullable()->unique()->constrained('usuarios');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }
};
