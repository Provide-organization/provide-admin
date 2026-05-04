<?php

namespace App\Services;

use App\Models\TenantInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Provisionamento enxuto (dev/TCC):
 *
 *   1. Cria database + user PostgreSQL isolados (tenant_{slug}, tenant_{slug}_user)
 *   2. Executa migrations + seeder no container `instancia` compartilhado,
 *      forçando DB_DATABASE=tenant_{slug} via env na chamada `docker exec`.
 *   3. Marca TenantInstance como 'active'.
 *
 * Em produção, substituir por:
 *   - container dedicado por cliente (isolamento de processo)
 *   - par de chaves RS256 dedicado
 *   - nginx com server block dedicado
 * (ver ARQUITETURA_MULTI_INSTANCIA.md — roadmap pós-TCC).
 */
class DatabaseProvisioningService
{
    public function provision(TenantInstance $instance): void
    {
        try {
            // 1) Databases isoladas
            $this->createDatabase($instance->db_name, $instance->db_username);
            $this->createDatabase($instance->db_logs_name, $instance->db_username);

            // 2) Migrations do banco principal
            $this->runMigrations($instance->db_name);
            $this->runSeeder($instance->db_name);
            $this->assertTenantUsuariosTable($instance->db_name);

            // 3) Migrations do banco de logs (pasta separada)
            $this->runLogMigrations($instance->db_logs_name);

            // 4) Marca ativo
            $instance->update([
                'status'         => 'active',
                'container_name' => env('INSTANCIA_CONTAINER', 'instancia-backend'),
                'provisioned_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $instance->update(['status' => 'failed']);
            Log::error('Falha no provisionamento do tenant', [
                'slug'  => $instance->slug,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function createDatabase(string $dbName, string $dbUser): void
    {
        $dbExists = DB::select('SELECT 1 FROM pg_database WHERE datname = ?', [$dbName]);
        if (empty($dbExists)) {
            $pdo = DB::connection()->getPdo();
            $pdo->exec("CREATE DATABASE \"{$dbName}\"");
        }

        $userExists = DB::select('SELECT 1 FROM pg_user WHERE usename = ?', [$dbUser]);
        if (empty($userExists)) {
            $password = 'tenant_' . Str::random(16);
            DB::statement("CREATE USER \"{$dbUser}\" WITH PASSWORD '{$password}'");
        }

        // Garante privilégios nos dois bancos (principal + logs)
        DB::statement("GRANT ALL PRIVILEGES ON DATABASE \"{$dbName}\" TO \"{$dbUser}\"");
        $this->grantSchemaAccess($dbName, $dbUser);
    }

    private function grantSchemaAccess(string $dbName, string $dbUser): void
    {
        $masterUser = config('database.connections.pgsql.username');
        $masterPass = config('database.connections.pgsql.password');
        $host       = config('database.connections.pgsql.host');
        $port       = config('database.connections.pgsql.port', 5432);

        $dsn    = "pgsql:host={$host};port={$port};dbname={$dbName}";
        $tmpPdo = new \PDO($dsn, $masterUser, $masterPass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $tmpPdo->exec("GRANT ALL ON SCHEMA public TO \"{$dbUser}\"");
    }

    private function runMigrations(string $dbName): void
    {
        $out = $this->execInInstancia($dbName, 'php artisan migrate --force');
        if ($this->outputIndicatesFailure($out)) {
            throw new \RuntimeException(
                "Migrations falharam para {$dbName}. Saída: " . substr($out, 0, 500)
            );
        }
    }

    private function runLogMigrations(string $dbLogsName): void
    {
        $out = $this->execInInstancia(
            $dbLogsName,
            'php artisan migrate --force --path=database/migrations/logs'
        );
        if ($this->outputIndicatesFailure($out)) {
            throw new \RuntimeException(
                "Migrations de log falharam para {$dbLogsName}. Saída: " . substr($out, 0, 500)
            );
        }
    }

    private function runSeeder(string $dbName): void
    {
        // Somente referência (RBAC, módulos, cidades, etc.). Não rodar PessoaSeeder/
        // OperacionalSeeder aqui — isso populava toda organização nova com usuários e
        // abrigos da demo (Lajeado). O admin inicial vem do payload admin_inicial na
        // criação da org; o ambiente demo recebe seed operacional via setup-dev.sh.
        $out = $this->execInInstancia(
            $dbName,
            'php artisan db:seed --class=ReferenceSeeder --force'
        );
        if ($this->outputIndicatesFailure($out)) {
            throw new \RuntimeException(
                "Seeder falhou para {$dbName}. Saída: " . substr($out, 0, 500)
            );
        }
    }

    /**
     * Roda um comando Artisan dentro do container `instancia` compartilhado,
     * sobrescrevendo DB_DATABASE via env para apontar pro banco do tenant.
     *
     * Limpa DB_URL: se estiver definido no .env do container, o Laravel pode ignorar
     * DB_DATABASE e manter o banco errado (ex.: tenant_demo para todo provisionamento).
     */
    private function execInInstancia(string $dbName, string $artisanCmd): string
    {
        $container = env('INSTANCIA_CONTAINER', 'instancia-backend');
        $db        = escapeshellarg($dbName);
        $ct        = escapeshellarg($container);
        $cmd       = "docker exec -e DB_DATABASE={$db} -e DB_URL= {$ct} {$artisanCmd} 2>&1";

        return (string) (shell_exec($cmd) ?? '');
    }

    private function outputIndicatesFailure(string $output): bool
    {
        $patterns = [
            'docker: not found', 'No such container', 'Error response from daemon',
            'permission denied', 'Cannot connect to the Docker daemon',
            'SQLSTATE', 'Error:', 'Exception',
        ];
        foreach ($patterns as $p) {
            if (stripos($output, $p) !== false) return true;
        }
        return false;
    }

    private function assertTenantUsuariosTable(string $dbName): void
    {
        $masterUser = config('database.connections.pgsql.username');
        $masterPass = config('database.connections.pgsql.password');
        $host       = config('database.connections.pgsql.host');
        $port       = config('database.connections.pgsql.port', 5432);

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";
        $pdo = new \PDO($dsn, $masterUser, $masterPass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $pdo->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='usuarios' LIMIT 1"
        );
        if (! $stmt || ! $stmt->fetchColumn()) {
            throw new \RuntimeException(
                "Após migrate/seed a tabela 'usuarios' não existe em {$dbName}."
            );
        }
    }
}
