<?php

namespace App\Services;

use App\Models\TenantInstance;
use Illuminate\Support\Facades\DB;

class TenantConnectionService
{
    /**
     * Retorna o nome da conexão dinâmica para o banco do tenant.
     * Usa as credenciais master do PostgreSQL — o superusuário tem acesso a todos os databases.
     */
    public function getConnection(string $slug): string
    {
        $instance = TenantInstance::where('slug', $slug)->firstOrFail();

        $connectionName = "tenant_{$slug}";

        // Configura conexão dinâmica herdando tudo da conexão pgsql padrão,
        // substituindo apenas o database name.
        $base = config('database.connections.pgsql');

        // Sem remover `url`, o Laravel prioriza DB_URL e ignora o `database` trocado aqui.
        $base = array_merge($base, [
            'database' => $instance->db_name ?? "tenant_{$slug}",
        ]);
        unset($base['url']);

        config(["database.connections.{$connectionName}" => $base]);

        // Limpa cache de conexão para forçar reconnect com novo database
        DB::purge($connectionName);

        return $connectionName;
    }
}
