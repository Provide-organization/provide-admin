<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TenantAbrigoService
{
    public function __construct(private readonly TenantConnectionService $connService) {}

    // ── Listagem ──────────────────────────────────────────────────────────────

    public function index(string $slug): array
    {
        $conn = $this->connService->getConnection($slug);

        return DB::connection($conn)
            ->table('abrigos as a')
            ->leftJoin('cidades as c', 'c.id', '=', 'a.cidade_id')
            ->leftJoin('pessoas as p', function ($j) {
                $j->on('p.id', '=', 'a.gestor_id')->whereNull('p.deleted_at');
            })
            ->whereNull('a.deleted_at')
            ->select(
                'a.id',
                'a.nome',
                'a.cep',
                'a.telefone',
                'a.capacidade_atual',
                'a.capacidade_maxima',
                'a.status',
                'a.cidade_id',
                'c.nome as cidade_nome',
                'a.gestor_id',
                'p.nome_completo as gestor_nome',
                'a.created_at',
            )
            ->orderBy('a.nome')
            ->get()
            ->map(function ($row) {
                $r = (array) $row;
                $max = max(1, (int) $r['capacidade_maxima']);
                $r['lotacao_percentual'] = round(($r['capacidade_atual'] / $max) * 100, 1);
                $r['esta_lotado']        = $r['capacidade_atual'] >= $r['capacidade_maxima'];
                $r['cidade']            = $r['cidade_nome'] ? ['id' => $r['cidade_id'], 'nome' => $r['cidade_nome']] : null;
                $r['gestor']            = $r['gestor_id'] ? ['id' => $r['gestor_id'], 'nome_completo' => $r['gestor_nome']] : null;
                unset($r['cidade_nome'], $r['gestor_nome']);
                return $r;
            })
            ->toArray();
    }

    // ── Criação ───────────────────────────────────────────────────────────────

    public function store(string $slug, array $data): array
    {
        $conn = $this->connService->getConnection($slug);
        $now  = now();

        $id = DB::connection($conn)->table('abrigos')->insertGetId([
            'nome'               => $data['nome'],
            'cep'                => $data['cep']        ?? null,
            'telefone'           => $data['telefone']   ?? null,
            'capacidade_atual'   => $data['capacidade_atual'],
            'capacidade_maxima'  => $data['capacidade_maxima'],
            'status'             => $data['status'] ?? true,
            'cidade_id'          => $data['cidade_id'],
            'gestor_id'          => $data['gestor_id'] ?? null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        return $this->findById($conn, $id);
    }

    // ── Atualização ───────────────────────────────────────────────────────────

    public function update(string $slug, int $abrigoId, array $data): array
    {
        $conn = $this->connService->getConnection($slug);
        $now  = now();

        $payload = array_filter([
            'nome'              => $data['nome']             ?? null,
            'cep'               => $data['cep']              ?? null,
            'telefone'          => $data['telefone']         ?? null,
            'capacidade_atual'  => $data['capacidade_atual'] ?? null,
            'capacidade_maxima' => $data['capacidade_maxima'] ?? null,
            'cidade_id'         => $data['cidade_id']        ?? null,
        ], fn($v) => !is_null($v));

        if (array_key_exists('status', $data)) {
            $payload['status'] = $data['status'];
        }
        if (array_key_exists('gestor_id', $data)) {
            $payload['gestor_id'] = $data['gestor_id'];
        }

        if (!empty($payload)) {
            $payload['updated_at'] = $now;
            DB::connection($conn)->table('abrigos')
                ->where('id', $abrigoId)
                ->whereNull('deleted_at')
                ->update($payload);
        }

        return $this->findById($conn, $abrigoId);
    }

    // ── Exclusão (soft delete) ────────────────────────────────────────────────

    public function destroy(string $slug, int $abrigoId): void
    {
        $conn = $this->connService->getConnection($slug);

        DB::connection($conn)->table('abrigos')
            ->where('id', $abrigoId)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    // ── Toggle status ─────────────────────────────────────────────────────────

    public function toggleStatus(string $slug, int $abrigoId): array
    {
        $conn   = $this->connService->getConnection($slug);
        $abrigo = DB::connection($conn)->table('abrigos')
            ->where('id', $abrigoId)
            ->whereNull('deleted_at')
            ->first();

        if (!$abrigo) {
            throw new \RuntimeException('Abrigo não encontrado no tenant.');
        }

        DB::connection($conn)->table('abrigos')
            ->where('id', $abrigoId)
            ->update(['status' => !$abrigo->status, 'updated_at' => now()]);

        return $this->findById($conn, $abrigoId);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    public function findById(string $conn, int $abrigoId): array
    {
        $row = DB::connection($conn)
            ->table('abrigos as a')
            ->leftJoin('cidades as c', 'c.id', '=', 'a.cidade_id')
            ->leftJoin('pessoas as p', function ($j) {
                $j->on('p.id', '=', 'a.gestor_id')->whereNull('p.deleted_at');
            })
            ->where('a.id', $abrigoId)
            ->whereNull('a.deleted_at')
            ->select(
                'a.id', 'a.nome', 'a.cep', 'a.telefone',
                'a.capacidade_atual', 'a.capacidade_maxima', 'a.status',
                'a.cidade_id', 'c.nome as cidade_nome',
                'a.gestor_id', 'p.nome_completo as gestor_nome',
                'a.created_at',
            )
            ->first();

        if (!$row) {
            throw new \RuntimeException('Abrigo não encontrado no tenant.');
        }

        $r = (array) $row;
        $max = max(1, (int) $r['capacidade_maxima']);
        $r['lotacao_percentual'] = round(($r['capacidade_atual'] / $max) * 100, 1);
        $r['esta_lotado']        = $r['capacidade_atual'] >= $r['capacidade_maxima'];
        $r['cidade']            = $r['cidade_nome'] ? ['id' => $r['cidade_id'], 'nome' => $r['cidade_nome']] : null;
        $r['gestor']            = $r['gestor_id'] ? ['id' => $r['gestor_id'], 'nome_completo' => $r['gestor_nome']] : null;
        unset($r['cidade_nome'], $r['gestor_nome']);

        return $r;
    }
}
