<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TenantUsuarioPermissaoService
{
    public function __construct(private readonly TenantConnectionService $connService) {}

    /**
     * @return list<array{id:int, slug:string, nome:string, descricao:?string, modulo_slug:?string}>
     */
    public function listCatalog(string $slug): array
    {
        $conn = $this->connService->getConnection($slug);

        $rows = DB::connection($conn)
            ->table('permissoes as p')
            ->leftJoin('modulos as m', 'm.id', '=', 'p.modulo_id')
            ->orderBy('p.slug')
            ->select('p.id', 'p.slug', 'p.nome', 'p.descricao', 'm.slug as modulo_slug')
            ->get();

        return $rows->map(fn ($r) => [
            'id'            => (int) $r->id,
            'slug'          => (string) $r->slug,
            'nome'          => (string) $r->nome,
            'descricao'     => $r->descricao,
            'modulo_slug'   => $r->modulo_slug,
        ])->values()->all();
    }

    /**
     * @return list<array{id:int, permissao_id:int, slug:string, efeito:string}>
     */
    public function listOverrides(string $slug, int $usuarioId): array
    {
        $conn = $this->connService->getConnection($slug);

        return DB::connection($conn)
            ->table('usuario_permissoes as up')
            ->join('permissoes as p', 'p.id', '=', 'up.permissao_id')
            ->where('up.usuario_id', $usuarioId)
            ->whereNull('up.tipo_escopo')
            ->whereNull('up.escopo_id')
            ->orderBy('p.slug')
            ->select('up.id', 'up.permissao_id', 'up.efeito', 'p.slug')
            ->get()
            ->map(fn ($row) => [
                'id'           => (int) $row->id,
                'permissao_id' => (int) $row->permissao_id,
                'slug'         => (string) $row->slug,
                'efeito'       => (string) $row->efeito,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{permissao_id:int, efeito:string}>  $overrides
     */
    public function syncGlobalOverrides(string $slug, int $usuarioId, array $overrides): void
    {
        $conn = $this->connService->getConnection($slug);
        $now  = now();

        DB::connection($conn)->transaction(function () use ($conn, $usuarioId, $overrides, $now): void {
            $this->replaceGlobalOverrides($conn, $usuarioId, $overrides, $now);
        });

        $this->forgetInstanciaPermissionCache($slug, $usuarioId);
    }

    /**
     * Atualiza nível de acesso (perfil por nivel) e overrides em uma única transação.
     *
     * @param  list<array{permissao_id:int, efeito:string}>  $overrides
     */
    public function syncGestaoPermissoes(string $slug, int $usuarioId, int $role, array $overrides): void
    {
        $conn = $this->connService->getConnection($slug);
        $now  = now();

        DB::connection($conn)->transaction(function () use ($conn, $usuarioId, $role, $overrides, $now): void {
            $perfil = DB::connection($conn)->table('perfis')->where('nivel', $role)->first();
            if (! $perfil) {
                throw new \RuntimeException("Perfil de nível {$role} não encontrado no tenant.");
            }

            DB::connection($conn)->table('usuario_perfis')
                ->where('usuario_id', $usuarioId)
                ->delete();

            DB::connection($conn)->table('usuario_perfis')->insert([
                'usuario_id'  => $usuarioId,
                'perfil_id'   => $perfil->id,
                'tipo_escopo' => null,
                'escopo_id'   => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $this->replaceGlobalOverrides($conn, $usuarioId, $overrides, $now);
        });

        $this->forgetInstanciaPermissionCache($slug, $usuarioId);
    }

    /**
     * Slugs herdados pelo perfil padrão de um nível (para preview no painel admin).
     *
     * @return list<string>
     */
    public function listSlugsForNivel(string $slug, int $nivel): array
    {
        $conn   = $this->connService->getConnection($slug);
        $perfil = DB::connection($conn)->table('perfis')->where('nivel', $nivel)->first();
        if (! $perfil) {
            return [];
        }

        return DB::connection($conn)
            ->table('perfil_permissoes as pp')
            ->join('permissoes as p', 'p.id', '=', 'pp.permissao_id')
            ->where('pp.perfil_id', $perfil->id)
            ->pluck('p.slug')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Estado atual: nível, cada permissão do catálogo, herdado do perfil, override e efeito final.
     *
     * @return array{role:int, role_nome:string, items:list<array<string,mixed>>}
     */
    public function getPermissoesResumo(string $slug, int $usuarioId): array
    {
        $conn = $this->connService->getConnection($slug);

        $userRow = DB::connection($conn)
            ->table('usuario_perfis as up')
            ->join('perfis as pf', 'pf.id', '=', 'up.perfil_id')
            ->join('usuarios as u', 'u.id', '=', 'up.usuario_id')
            ->where('up.usuario_id', $usuarioId)
            ->whereNull('u.deleted_at')
            ->select('pf.nivel as role', 'pf.nome as role_nome')
            ->orderBy('pf.nivel')
            ->first();

        if (! $userRow) {
            throw new \RuntimeException('Usuário não encontrado no tenant.');
        }

        $perfilIds = DB::connection($conn)->table('usuario_perfis')
            ->where('usuario_id', $usuarioId)
            ->pluck('perfil_id')
            ->all();

        $perfilSlugs = [];
        if (! empty($perfilIds)) {
            $perfilSlugs = DB::connection($conn)
                ->table('perfil_permissoes as pp')
                ->join('permissoes as p', 'p.id', '=', 'pp.permissao_id')
                ->whereIn('pp.perfil_id', $perfilIds)
                ->pluck('p.slug')
                ->unique()
                ->values()
                ->all();
        }

        $overrideRows = DB::connection($conn)
            ->table('usuario_permissoes as up')
            ->join('permissoes as p', 'p.id', '=', 'up.permissao_id')
            ->where('up.usuario_id', $usuarioId)
            ->whereNull('up.tipo_escopo')
            ->whereNull('up.escopo_id')
            ->get(['up.permissao_id', 'up.efeito', 'p.slug']);

        $overrideByPermId = [];
        $grantSlugs       = [];
        $denySlugs        = [];
        foreach ($overrideRows as $r) {
            $overrideByPermId[(int) $r->permissao_id] = (string) $r->efeito;
            if ($r->efeito === 'grant') {
                $grantSlugs[] = $r->slug;
            }
            if ($r->efeito === 'deny') {
                $denySlugs[] = $r->slug;
            }
        }

        $merged = array_values(array_unique(array_merge($perfilSlugs, $grantSlugs)));
        $final  = array_values(array_diff($merged, $denySlugs));

        $catalog = DB::connection($conn)
            ->table('permissoes as p')
            ->leftJoin('modulos as m', 'm.id', '=', 'p.modulo_id')
            ->orderBy('p.slug')
            ->select('p.id', 'p.slug', 'p.nome', 'p.descricao', 'm.slug as modulo_slug')
            ->get();

        $items = [];
        foreach ($catalog as $p) {
            $slugP       = (string) $p->slug;
            $fromPerfil  = in_array($slugP, $perfilSlugs, true);
            $override    = $overrideByPermId[(int) $p->id] ?? null;
            $effective   = in_array($slugP, $final, true);
            $items[]     = [
                'id'           => (int) $p->id,
                'slug'         => $slugP,
                'nome'         => (string) $p->nome,
                'descricao'    => $p->descricao,
                'modulo_slug'  => $p->modulo_slug,
                'from_perfil'  => $fromPerfil,
                'override'     => $override,
                'effective'    => $effective,
            ];
        }

        return [
            'role'      => (int) $userRow->role,
            'role_nome' => (string) $userRow->role_nome,
            'items'     => $items,
        ];
    }

    /**
     * @param  list<array{permissao_id:int, efeito:string}>  $overrides
     */
    private function replaceGlobalOverrides(string $conn, int $usuarioId, array $overrides, $now): void
    {
        $seen = [];

        DB::connection($conn)->table('usuario_permissoes')
            ->where('usuario_id', $usuarioId)
            ->whereNull('tipo_escopo')
            ->whereNull('escopo_id')
            ->delete();

        foreach ($overrides as $row) {
            $pid = (int) $row['permissao_id'];
            if (isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;

            DB::connection($conn)->table('usuario_permissoes')->insert([
                'usuario_id'   => $usuarioId,
                'permissao_id' => $pid,
                'efeito'       => $row['efeito'],
                'tipo_escopo'  => null,
                'escopo_id'    => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    /**
     * Melhor esforço: se admin e instância compartilham o mesmo Redis e prefixo,
     * invalida o cache do PermissionResolver da instância.
     */
    private function forgetInstanciaPermissionCache(string $slug, int $usuarioId): void
    {
        $dbName = 'tenant_' . $slug;
        $key     = 'perms:u:' . $dbName . ':' . $usuarioId;

        try {
            Cache::forget($key);
        } catch (\Throwable) {
            // ignore
        }
    }
}
