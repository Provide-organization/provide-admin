<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\TenantConnectionService;
use App\Services\TenantUsuarioPermissaoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TenantUsuarioPermissaoController extends Controller
{
    public function __construct(
        private readonly TenantUsuarioPermissaoService $service,
        private readonly TenantConnectionService $connService,
    ) {}

    public function indexCatalog(string $organizacao_slug): JsonResponse
    {
        if (! $this->assertPlatformLevel1()) {
            return $this->forbiddenL1();
        }

        try {
            return response()->json(['data' => $this->service->listCatalog($organizacao_slug)]);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function showOverrides(string $organizacao_slug, int $usuario_id): JsonResponse
    {
        if (! $this->assertPlatformLevel1()) {
            return $this->forbiddenL1();
        }

        try {
            return response()->json(['data' => $this->service->listOverrides($organizacao_slug, $usuario_id)]);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function syncOverrides(Request $request, string $organizacao_slug, int $usuario_id): JsonResponse
    {
        if (! $this->assertPlatformLevel1()) {
            return $this->forbiddenL1();
        }

        $conn = $this->connService->getConnection($organizacao_slug);

        $v = Validator::make($request->all(), [
            'overrides'                => ['present', 'array'],
            'overrides.*.permissao_id' => ['required', 'integer', 'distinct'],
            'overrides.*.efeito'       => ['required', 'string', Rule::in(['grant', 'deny'])],
        ]);

        if ($v->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $v->errors(),
                'code'    => 'VALIDATION_ERROR',
            ], 422);
        }

        $overrides = $v->validated()['overrides'];

        foreach ($overrides as $row) {
            $exists = DB::connection($conn)
                ->table('permissoes')
                ->where('id', $row['permissao_id'])
                ->exists();
            if (! $exists) {
                return response()->json([
                    'message' => 'Permissão inválida.',
                    'code'    => 'INVALID_PERMISSION',
                ], 422);
            }
        }

        try {
            $this->service->syncGlobalOverrides($organizacao_slug, $usuario_id, $overrides);

            return response()->json([
                'message' => 'Permissões personalizadas atualizadas.',
                'data'    => $this->service->listOverrides($organizacao_slug, $usuario_id),
            ]);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function permissoesResumo(string $organizacao_slug, int $usuario_id): JsonResponse
    {
        if (! $this->assertPlatformLevel1()) {
            return $this->forbiddenL1();
        }

        try {
            return response()->json(['data' => $this->service->getPermissoesResumo($organizacao_slug, $usuario_id)]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function slugsPorNivel(string $organizacao_slug, int $nivel): JsonResponse
    {
        if (! $this->assertPlatformLevel1()) {
            return $this->forbiddenL1();
        }

        if ($nivel < 2 || $nivel > 5) {
            return response()->json(['message' => 'Nível inválido.', 'code' => 'INVALID_LEVEL'], 422);
        }

        try {
            return response()->json([
                'data' => ['slugs' => $this->service->listSlugsForNivel($organizacao_slug, $nivel)],
            ]);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function syncGestao(Request $request, string $organizacao_slug, int $usuario_id): JsonResponse
    {
        if (! $this->assertPlatformLevel1()) {
            return $this->forbiddenL1();
        }

        $conn = $this->connService->getConnection($organizacao_slug);

        $v = Validator::make($request->all(), [
            'role'                     => ['required', 'integer', 'min:2', 'max:5'],
            'overrides'                => ['present', 'array'],
            'overrides.*.permissao_id' => ['required', 'integer', 'distinct'],
            'overrides.*.efeito'       => ['required', 'string', Rule::in(['grant', 'deny'])],
        ]);

        if ($v->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors'  => $v->errors(),
                'code'    => 'VALIDATION_ERROR',
            ], 422);
        }

        $role      = (int) $v->validated()['role'];
        $overrides = $v->validated()['overrides'];

        foreach ($overrides as $row) {
            $exists = DB::connection($conn)
                ->table('permissoes')
                ->where('id', $row['permissao_id'])
                ->exists();
            if (! $exists) {
                return response()->json([
                    'message' => 'Permissão inválida.',
                    'code'    => 'INVALID_PERMISSION',
                ], 422);
            }
        }

        try {
            $this->service->syncGestaoPermissoes($organizacao_slug, $usuario_id, $role, $overrides);

            return response()->json([
                'message' => 'Nível e permissões atualizados.',
                'data'    => $this->service->getPermissoesResumo($organizacao_slug, $usuario_id),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'SYNC_ERROR'], 422);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    private function assertPlatformLevel1(): bool
    {
        /** @var Usuario|null $user */
        $user = auth()->guard('api')->user();
        if (! $user) {
            return false;
        }
        $user->loadMissing('perfis');
        $min = $user->perfis->min('nivel');

        return (int) $min === 1;
    }

    private function forbiddenL1(): JsonResponse
    {
        return response()->json([
            'message' => 'Apenas administradores L1 da plataforma podem gerenciar permissões de operadores no tenant.',
            'code'    => 'PLATFORM_L1_REQUIRED',
        ], 403);
    }

    private function tenantDbUnavailable(\Throwable $e): JsonResponse
    {
        $msg = $e->getMessage();

        return response()->json([
            'message' => 'O banco de dados do tenant não está acessível.',
            'code'    => 'TENANT_DB_UNAVAILABLE',
            'detail'  => app()->isLocal() ? $msg : null,
        ], 503);
    }
}
