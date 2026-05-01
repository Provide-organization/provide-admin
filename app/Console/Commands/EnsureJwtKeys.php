<?php

namespace App\Console\Commands;

use App\Services\KeyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Garante que exista um par RSA em storage/keys/ para assinar tokens JWT (RS256).
 *
 * O par é compartilhado entre admin-backend e instancia-backend via bind mount
 * ./deploy/keys. A primeira container a subir gera o par; as demais apenas leem.
 *
 * Também sincroniza a chave pública em platform_keys (para JWKS/inspeção).
 */
class EnsureJwtKeys extends Command
{
    protected $signature = 'jwt:keys:ensure {--force : Regenera mesmo que já existam}';
    protected $description = 'Garante par RSA em storage/keys/ e sincroniza a pública em platform_keys.';

    public function handle(KeyService $keys): int
    {
        $dir         = storage_path('keys');
        $privatePath = $dir . '/jwt-private.pem';
        $publicPath  = $dir . '/jwt-public.pem';
        $kidPath     = $dir . '/jwt-kid.txt';

        File::ensureDirectoryExists($dir, 0700);

        $exists = File::exists($privatePath) && File::exists($publicPath) && File::exists($kidPath);

        if ($exists && ! $this->option('force')) {
            $kid = trim((string) File::get($kidPath));
            $this->info("JWT keys já presentes (kid={$kid}).");
            $this->syncPublicKey($keys, $kid, File::get($publicPath));
            $this->fixKeysOwnershipIfRoot($privatePath, $publicPath, $kidPath);

            return self::SUCCESS;
        }

        $this->info('Gerando novo par RSA compartilhado…');
        $pair = $keys->generateKeyPair();

        File::put($privatePath, $pair['private']);
        File::put($publicPath, $pair['public']);
        File::put($kidPath, $pair['kid']);

        @chmod($privatePath, 0600);
        @chmod($publicPath, 0644);
        @chmod($kidPath, 0644);

        $this->fixKeysOwnershipIfRoot($privatePath, $publicPath, $kidPath);

        $this->syncPublicKey($keys, $pair['kid'], $pair['public']);

        $this->info("Par RSA gerado. kid={$pair['kid']}");
        return self::SUCCESS;
    }

    /**
     * `docker exec` sem usuário roda como root: cria PEM 0600 root:root e o PHP-FPM (www-data)
     * não consegue ler a chave privada — login quebra com "Could not create token" / Permission denied.
     */
    private function fixKeysOwnershipIfRoot(string $privatePath, string $publicPath, string $kidPath): void
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return;
        }

        $user = 'www-data';
        foreach ([$privatePath, $publicPath, $kidPath] as $path) {
            if (! is_file($path)) {
                continue;
            }
            @chown($path, $user);
            @chgrp($path, $user);
        }

        if (is_file($privatePath)) {
            @chmod($privatePath, 0600);
        }
        if (is_file($publicPath)) {
            @chmod($publicPath, 0644);
        }
        if (is_file($kidPath)) {
            @chmod($kidPath, 0644);
        }
    }

    private function syncPublicKey(KeyService $keys, string $kid, string $publicKey): void
    {
        try {
            $keys->registerPlatformPublicKey($kid, $publicKey);
            $this->info('Chave pública sincronizada em platform_keys.');
        } catch (\Throwable $e) {
            $this->warn('Não foi possível sincronizar em platform_keys (o DB pode não estar migrado ainda): ' . $e->getMessage());
        }
    }
}
