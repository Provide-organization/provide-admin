<?php

namespace App\Console\Commands;

use App\Models\Organizacao;
use App\Services\OrganizacaoService;
use Illuminate\Console\Command;

/**
 * Cria uma organização e dispara o provisionamento completo (DB + container + nginx).
 *
 * Uso em dev (via setup-dev.sh):
 *   docker exec admin-backend php artisan tenant:create demo --nome="Organização Demo"
 *
 * Idempotente: se a org já existe, apenas informa e sai com código 0.
 * Se o provisionamento falhou previamente (status=failed), retenta.
 */
class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
        {slug : Slug único da organização (ex.: demo, lajeado)}
        {--nome= : Nome completo da organização}
        {--cnpj= : CNPJ (opcional)}';

    protected $description = 'Cria uma organização + container + banco (provisionamento completo).';

    public function handle(OrganizacaoService $service): int
    {
        $slug = (string) $this->argument('slug');
        $nome = (string) ($this->option('nome') ?? ucfirst($slug));
        $cnpj = $this->option('cnpj');

        $this->info("→ tenant:create {$slug}");

        $existing = Organizacao::with('tenantInstance')->where('slug', $slug)->first();

        if ($existing) {
            $status = $existing->tenantInstance?->status ?? 'ausente';
            $this->line("  Organização já existe (status: {$status}).");

            if ($existing->tenantInstance && $existing->tenantInstance->status !== 'active') {
                $this->info('  Retentando provisionamento...');
                try {
                    $service->reprovision($existing->tenantInstance);
                    $this->info('  ✓ Reprovisionado com sucesso.');
                } catch (\Throwable $e) {
                    $this->error('  ✗ Falha: ' . $e->getMessage());
                    return self::FAILURE;
                }
            } else {
                $this->info('  Nada a fazer (já ativo).');
            }
            return self::SUCCESS;
        }

        try {
            $org = $service->store(array_filter([
                'nome'  => $nome,
                'slug'  => $slug,
                'cnpj'  => $cnpj,
                'ativo' => true,
            ], static fn ($v) => $v !== null));

            $status = $org->tenantInstance?->status ?? 'ausente';
            $this->info("✓ Organização '{$slug}' criada (instância: {$status}).");

            if ($status !== 'active') {
                $this->warn(
                    "  Provisionamento registrou {$status} — veja logs em " .
                    'storage/logs/laravel.log ou rode `tenant:create` novamente.'
                );
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✗ Falha: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
