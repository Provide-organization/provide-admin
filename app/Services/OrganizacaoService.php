<?php

namespace App\Services;

use App\Models\Organizacao;
use App\Models\TenantInstance;
use Illuminate\Database\Eloquent\Collection;

class OrganizacaoService
{
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

        // Registra a instância do tenant em estado provisioning
        // (provisionamento real ocorre na Fase 2/3 — aqui registra o intent)
        TenantInstance::create([
            'organizacao_id' => $organizacao->id,
            'slug'           => $organizacao->slug,
            'container_name' => "tenant-{$organizacao->slug}",
            'db_name'        => "tenant_{$organizacao->slug}",
            'db_username'    => "tenant_{$organizacao->slug}_user",
            'status'         => 'provisioning',
        ]);

        return $organizacao->load('tenantInstance');
    }

    public function update(Organizacao $organizacao, array $data): Organizacao
    {
        $organizacao->update($data);
        return $organizacao->fresh(['tenantInstance']);
    }

    public function destroy(Organizacao $organizacao): void
    {
        $organizacao->tenantInstance?->delete();
        $organizacao->delete();
    }
}
