<?php

namespace App\Services;

use App\Models\Organizacao;
use App\Models\TenantInstance;
use Illuminate\Database\Eloquent\Collection;

class OrganizacaoService
{
    public function __construct(
        private readonly DatabaseProvisioningService $provisioning,
        private readonly TenantUsuarioService $tenantUsuarios,
    ) {}

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

        // Separa o payload do admin_inicial (não pertence à tabela organizacoes)
        $adminPayload = $data['admin_inicial'] ?? null;
        unset($data['admin_inicial']);

        $organizacao = Organizacao::create($data);

        $instance = TenantInstance::create([
            'organizacao_id' => $organizacao->id,
            'slug'           => $organizacao->slug,
            'container_name' => "tenant-{$organizacao->slug}",
            'db_name'        => "tenant_{$organizacao->slug}",
            'db_username'    => "tenant_{$organizacao->slug}_user",
            'status'         => 'provisioning',
        ]);

        try {
            $this->provisioning->provision($instance);

            if (is_array($adminPayload) && !empty($adminPayload)) {
                $result = $this->seedOrgAdmin($organizacao->slug, $adminPayload);
                // Expõe a senha temporária como atributo da Organizacao (não persistido)
                // para que o Resource possa incluí-la na resposta (one-time display).
                $organizacao->setAttribute('admin_inicial', [
                    'email'            => $result['usuario']['email'],
                    'nome'             => $result['usuario']['nome'] ?? $adminPayload['nome'],
                    'senha_temporaria' => $result['senha_temporaria'],
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Falha no provisionamento do tenant', [
                'slug'  => $organizacao->slug,
                'error' => $e->getMessage(),
            ]);
        }

        return $organizacao->load('tenantInstance');
    }

    /**
     * Cria o usuário admin inicial da organização no banco do tenant.
     * Perfil: admin_municipio (nivel = 1). Retorna payload com senha_temporaria.
     */
    private function seedOrgAdmin(string $slug, array $payload): array
    {
        return $this->tenantUsuarios->store($slug, [
            'nome'             => $payload['nome'],
            'email'            => $payload['email'],
            'cpf'              => $payload['cpf']   ?? null,
            'senha_temporaria' => $payload['senha_temp'] ?? null,
            'role'             => 1,
            'ativo'            => true,
        ]);
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
