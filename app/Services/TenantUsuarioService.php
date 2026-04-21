<?php

namespace App\Services;

use App\Support\TenantPermissionCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantUsuarioService
{
    public function __construct(private readonly TenantConnectionService $connService) {}

    // ── Listagem ──────────────────────────────────────────────────────────────

    public function index(string $slug): array
    {
        $conn = $this->connService->getConnection($slug);

        return DB::connection($conn)
            ->table('usuarios as u')
            ->leftJoin('pessoas as p', function ($j) {
                $j->on('p.usuario_id', '=', 'u.id')->whereNull('p.deleted_at');
            })
            ->leftJoin('usuario_perfis as up', 'up.usuario_id', '=', 'u.id')
            ->leftJoin('perfis as pf', 'pf.id', '=', 'up.perfil_id')
            ->whereNull('u.deleted_at')
            ->select(
                'u.id',
                'u.email',
                'u.ativo',
                'u.deve_trocar_senha',
                'u.created_at',
                'p.nome_completo as nome',
                'p.cpf',
                'pf.nivel as role',
                'pf.nome as role_nome'
            )
            ->orderBy('u.id')
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    // ── Criação ───────────────────────────────────────────────────────────────

    public function store(string $slug, array $data): array
    {
        $conn = $this->connService->getConnection($slug);
        $now  = now();

        // Remove soft-deleted com mesmo e-mail/CPF para liberar unique constraint
        DB::connection($conn)->table('usuarios')
            ->whereNotNull('deleted_at')
            ->where('email', $data['email'])
            ->delete();

        if (!empty($data['cpf'])) {
            DB::connection($conn)->table('pessoas')
                ->whereNotNull('deleted_at')
                ->where('cpf', $data['cpf'])
                ->delete();
        }

        $senhaPlain = $data['senha_temporaria'] ?? $this->gerarSenha();

        $usuarioId = DB::connection($conn)->table('usuarios')->insertGetId([
            'email'             => $data['email'],
            'senha'             => Hash::make($senhaPlain),
            'ativo'             => $data['ativo'] ?? true,
            'deve_trocar_senha' => true,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        DB::connection($conn)->table('pessoas')->insert([
            'nome_completo' => $data['nome'],
            'cpf'           => !empty($data['cpf']) ? $data['cpf'] : null,
            'usuario_id'    => $usuarioId,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $role   = $data['role'] ?? 5;
        $perfil = DB::connection($conn)->table('perfis')->where('nivel', $role)->first();

        if (!$perfil) {
            throw new \RuntimeException("Perfil de nível {$role} não encontrado. Execute o ReferenceSeeder no tenant.");
        }

        DB::connection($conn)->table('usuario_perfis')->insert([
            'usuario_id'  => $usuarioId,
            'perfil_id'   => $perfil->id,
            'tipo_escopo' => null,
            'escopo_id'   => null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        TenantPermissionCache::forgetForUserOnConnection($conn, $usuarioId);

        $usuario = $this->findById($conn, $usuarioId);

        return [
            'usuario'          => $usuario,
            'senha_temporaria' => $senhaPlain,
        ];
    }

    // ── Atualização ───────────────────────────────────────────────────────────

    public function update(string $slug, int $usuarioId, array $data): array
    {
        $conn = $this->connService->getConnection($slug);
        $now  = now();

        if (isset($data['nome'])) {
            $pessoa = DB::connection($conn)->table('pessoas')
                ->where('usuario_id', $usuarioId)
                ->whereNull('deleted_at')
                ->first();

            if ($pessoa) {
                DB::connection($conn)->table('pessoas')
                    ->where('id', $pessoa->id)
                    ->update(['nome_completo' => $data['nome'], 'updated_at' => $now]);
            } else {
                DB::connection($conn)->table('pessoas')->insert([
                    'nome_completo' => $data['nome'],
                    'usuario_id'    => $usuarioId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }

        if (isset($data['role'])) {
            $perfil = DB::connection($conn)->table('perfis')->where('nivel', $data['role'])->first();
            if ($perfil) {
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
            }
        }

        if (isset($data['ativo'])) {
            DB::connection($conn)->table('usuarios')
                ->where('id', $usuarioId)
                ->update(['ativo' => $data['ativo'], 'updated_at' => $now]);
        }

        if (isset($data['role']) || isset($data['ativo'])) {
            TenantPermissionCache::forgetForUserOnConnection($conn, $usuarioId);
        }

        return $this->findById($conn, $usuarioId);
    }

    // ── Exclusão (soft delete) ────────────────────────────────────────────────

    public function destroy(string $slug, int $usuarioId): void
    {
        $conn = $this->connService->getConnection($slug);

        DB::connection($conn)->table('usuario_perfis')
            ->where('usuario_id', $usuarioId)
            ->delete();

        DB::connection($conn)->table('usuarios')
            ->where('id', $usuarioId)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        TenantPermissionCache::forgetForUserOnConnection($conn, $usuarioId);
    }

    // ── Reset de senha ────────────────────────────────────────────────────────

    public function resetSenha(string $slug, int $usuarioId): string
    {
        $conn      = $this->connService->getConnection($slug);
        $novaSenha = $this->gerarSenha();

        DB::connection($conn)->table('usuarios')
            ->where('id', $usuarioId)
            ->update([
                'senha'             => Hash::make($novaSenha),
                'deve_trocar_senha' => true,
                'updated_at'        => now(),
            ]);

        return $novaSenha;
    }

    // ── Toggle ativo ──────────────────────────────────────────────────────────

    public function toggleAtivo(string $slug, int $usuarioId): array
    {
        $conn    = $this->connService->getConnection($slug);
        $usuario = DB::connection($conn)->table('usuarios')
            ->where('id', $usuarioId)
            ->whereNull('deleted_at')
            ->first();

        DB::connection($conn)->table('usuarios')
            ->where('id', $usuarioId)
            ->update(['ativo' => !$usuario->ativo, 'updated_at' => now()]);

        TenantPermissionCache::forgetForUserOnConnection($conn, $usuarioId);

        return $this->findById($conn, $usuarioId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function findById(string $conn, int $usuarioId): array
    {
        $row = DB::connection($conn)
            ->table('usuarios as u')
            ->leftJoin('pessoas as p', function ($j) {
                $j->on('p.usuario_id', '=', 'u.id')->whereNull('p.deleted_at');
            })
            ->leftJoin('usuario_perfis as up', 'up.usuario_id', '=', 'u.id')
            ->leftJoin('perfis as pf', 'pf.id', '=', 'up.perfil_id')
            ->where('u.id', $usuarioId)
            ->whereNull('u.deleted_at')
            ->select(
                'u.id',
                'u.email',
                'u.ativo',
                'u.deve_trocar_senha',
                'u.created_at',
                'p.nome_completo as nome',
                'p.cpf',
                'pf.nivel as role',
                'pf.nome as role_nome'
            )
            ->first();

        if (!$row) {
            throw new \RuntimeException('Usuário não encontrado no tenant.');
        }

        return (array) $row;
    }

    private function gerarSenha(): string
    {
        return 'Provide@' . strtoupper(Str::random(4));
    }
}
