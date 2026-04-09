<?php

namespace App\Services;

use App\Models\TenantInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseProvisioningService
{
    /**
     * Provisiona um novo banco de dados para o tenant:
     * 1. Cria o database e usuário PostgreSQL
     * 2. Roda migrations via docker exec no container tenant-demo
     * 3. Roda ReferenceSeeder (dados essenciais de RBAC, localização, etc.)
     * 4. Marca o TenantInstance como 'active'
     *
     * Em produção, isso deve ser movido para um Job assíncrono via queue.
     */
    public function provision(TenantInstance $instance): void
    {
        try {
            $this->createDatabase($instance->db_name, $instance->db_username);
            $this->runMigrations($instance->db_name);
            $this->runSeeder($instance->db_name);
            $this->assertTenantUsuariosTable($instance->db_name);

            $instance->update([
                'status'         => 'active',
                'provisioned_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $instance->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function createDatabase(string $dbName, string $dbUser): void
    {
        $dbExists = DB::select('SELECT 1 FROM pg_database WHERE datname = ?', [$dbName]);
        if (empty($dbExists)) {
            // CREATE DATABASE não pode rodar em transaction — usa PDO direto
            $pdo = DB::connection()->getPdo();
            $pdo->exec("CREATE DATABASE \"{$dbName}\"");
        }

        $userExists = DB::select('SELECT 1 FROM pg_user WHERE usename = ?', [$dbUser]);
        if (empty($userExists)) {
            $password = 'tenant_' . Str::random(16);
            DB::statement("CREATE USER \"{$dbUser}\" WITH PASSWORD '{$password}'");
            DB::statement("GRANT ALL PRIVILEGES ON DATABASE \"{$dbName}\" TO \"{$dbUser}\"");

            // Garante acesso ao schema public no novo banco
            $this->grantSchemaAccess($dbName, $dbUser);
        }
    }

    private function grantSchemaAccess(string $dbName, string $dbUser): void
    {
        $masterUser = config('database.connections.pgsql.username');
        $masterPass = config('database.connections.pgsql.password');
        $host       = config('database.connections.pgsql.host');
        $port       = config('database.connections.pgsql.port', 5432);

        // Conecta diretamente no novo banco para conceder privilégios no schema
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";
        $tempPdo = new \PDO($dsn, $masterUser, $masterPass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $tempPdo->exec("GRANT ALL ON SCHEMA public TO \"{$dbUser}\"");
    }

    private function runMigrations(string $dbName): void
    {
        $output = $this->dockerExec($dbName, 'php artisan migrate --force');

        // Se a saída contiver erro de conexão/comando ou estiver vazia, migrations não rodaram
        if ($this->outputIndicatesFailure($output)) {
            throw new \RuntimeException(
                "Migrations não puderam ser executadas no tenant '{$dbName}'. " .
                "Verifique se o container platform-backend foi reconstruído com docker-cli. " .
                "Saída: " . substr($output, 0, 300)
            );
        }
    }

    private function runSeeder(string $dbName): void
    {
        $output = $this->dockerExec($dbName, 'php artisan db:seed --class=ReferenceSeeder --force');

        if ($this->outputIndicatesFailure($output)) {
            throw new \RuntimeException(
                "Seeder não pôde ser executado no tenant '{$dbName}'. Saída: " . substr($output, 0, 300)
            );
        }
    }

    /**
     * Executa um comando no container tenant-demo com o banco do novo tenant.
     * Usa as credenciais master que têm acesso a todos os databases.
     */
    private function dockerExec(string $dbName, string $command): string
    {
        // Verifica se docker está disponível antes de tentar
        $dockerPath = trim(shell_exec('which docker 2>/dev/null') ?? '');
        if (empty($dockerPath)) {
            return ''; // Tratado como falha em outputIndicatesFailure()
        }

        $containerName = env('TENANT_RUNNER_CONTAINER', 'tenant-demo');
        $dbHost        = config('database.connections.pgsql.host');
        $dbUsername    = config('database.connections.pgsql.username');
        $dbPassword    = config('database.connections.pgsql.password');
        $dbPort        = config('database.connections.pgsql.port', 5432);

        // DB_URL / DATABASE_URL no .env do tenant sobrescrevem o efeito de DB_* no Laravel.
        // Zera explicitamente para o artisan usar só host + database + credenciais passadas aqui.
        $envFlags = implode(' ', [
            '-e DB_URL=',
            '-e DATABASE_URL=',
            '-e DB_CONNECTION=pgsql',
            '-e DB_DATABASE=' . escapeshellarg($dbName),
            '-e DB_HOST=' . escapeshellarg($dbHost),
            '-e DB_PORT=' . escapeshellarg((string) $dbPort),
            '-e DB_USERNAME=' . escapeshellarg($dbUsername),
            '-e DB_PASSWORD=' . escapeshellarg($dbPassword),
        ]);

        $fullCmd = "docker exec {$envFlags} {$containerName} {$command} 2>&1";
        $output  = shell_exec($fullCmd) ?? '';

        return $output;
    }

    /**
     * Detecta saídas que indicam que o comando não foi executado com sucesso.
     */
    private function outputIndicatesFailure(string $output): bool
    {
        if (trim($output) === '') {
            return true; // docker não encontrado ou retornou vazio
        }

        $errorPatterns = [
            'docker: not found',
            'No such container',
            'Error response from daemon',
            'permission denied',
            'Cannot connect to the Docker daemon',
            'SQLSTATE',
            'Error:',
        ];

        foreach ($errorPatterns as $pattern) {
            if (stripos($output, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Garante que as migrations realmente rodaram neste database (evita falso sucesso).
     */
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
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'usuarios' LIMIT 1"
        );

        if (! $stmt || ! $stmt->fetchColumn()) {
            throw new \RuntimeException(
                "Após migrate/seed, a tabela \"usuarios\" não existe em \"{$dbName}\". " .
                'Provável causa: DB_URL no .env do tenant apontando para outro banco — corrigido no docker exec; rode Reprovisionar de novo.'
            );
        }
    }
}
