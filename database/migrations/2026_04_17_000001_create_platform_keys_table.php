<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_keys', function (Blueprint $table) {
            $table->id();

            // platform | organizacao
            $table->string('owner_type', 32);

            // Preenchido só para owner_type = organizacao. Sem cascadeOnDelete:
            // queremos manter o histórico mesmo se a org for removida.
            $table->foreignId('organizacao_id')
                ->nullable()
                ->constrained('organizacoes')
                ->nullOnDelete();

            // Identificador do par de chaves (claim "kid" no JWT).
            $table->string('kid', 64)->unique();

            // Emissor do token (claim "iss"): "platform" para admin, "<slug>" para tenant.
            $table->string('issuer', 128);

            // Algoritmo assimétrico. Fixo em RS256 nesta fase.
            $table->string('algorithm', 16)->default('RS256');

            // Chave pública em PEM. Livre para consulta (JWKS, validação cruzada).
            $table->text('public_key');

            // Chave privada cifrada com Crypt::encrypt (AES-256, APP_KEY do admin).
            // Nullable porque a chave da própria plataforma permanece em disco
            // no container do admin — não é cifrada nem replicada.
            $table->text('private_key_enc')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();

            $table->index(['owner_type', 'organizacao_id']);
            $table->index('issuer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_keys');
    }
};
