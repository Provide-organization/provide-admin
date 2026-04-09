<?php

namespace App\Services;

use App\Models\Organizacao;
use App\Models\TenantInstance;
use Illuminate\Database\Eloquent\Collection;

class OrganizacaoService
{
    public function __construct(private readonly DatabaseProvisioningService $provisioning) {}

    public function index(): Collection
    {
        return Organizacao::with('tenantInstance')->orderBy('nome')->get();
    }

    public function store(array $data): Organizacao
    {
        Organizacao::withTrashed()
            ->whereNotNull('deleted_at')
            ->where(function ($q) use ($data) {
                $q->where('slug', $data['slug']);
                if (!empty($data['cnpj'])) {
                    $q->orWhere('cnpj', $data['cnpj']);
                }
            })
            ->forceDelete();

        $organizacao = Organizacao::create($data);

        $instance = TenantInstance::create([
            'organizacao_id' => $organizacao->id,
            'slug'           => $organizacao->slug,
            'container_name' => "tenant-{$organizacao->slug}",
            'db_name'        => "tenant_{$organizacao->slug}",
            'db_username'    => "tenant_{$organizacao->slug}_user",
            'status'         => 'provisioning',
        ]);

        // Provisiona banco, migrations e seeder de forma síncrona (Fase 2)
        try {
            $this->provisioning->provision($instance);
        } catch (\Throwable $e) {
            // Falha no provisionamento não desfaz a org — admin pode reprovisionare
            \Illuminate\Support\Facades\Log::error('Falha no provisionamento do tenant', [
                'slug'  => $organizacao->slug,
                'error' => $e->getMessage(),
            ]);
        }

        return $organizacao->load('tenantInstance');
    }

    public function update(Organizacao $organizacao, array $data): Organizacao
    {
        $organizacao->update($data);
        return $organizacao->fresh(['tenantInstance']);
    }

    /**
     * Retenta o provisionamento de um tenant (qualquer status).
     * Lança exceção se o docker não estiver disponível ou se as migrations falharem.
     */
    public function reprovision(TenantInstance $instance): void
    {
        $instance->update(['status' => 'provisioning', 'provisioned_at' => null]);

        $this->provisioning->provision($instance);
    }

    public function destroy(Organizacao $organizacao): void
    {
        $organizacao->tenantInstance?->delete();
        $organizacao->delete();
    }
}
