<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TenantUsuario\StoreTenantUsuarioRequest;
use App\Http\Requests\Api\V1\TenantUsuario\UpdateTenantUsuarioRequest;
use App\Services\TenantUsuarioService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class TenantUsuarioController extends Controller
{
    public function __construct(private readonly TenantUsuarioService $service) {}

    public function index(string $organizacao_slug): JsonResponse
    {
        try {
            $usuarios = $this->service->index($organizacao_slug);
            return response()->json(['data' => $usuarios]);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function store(StoreTenantUsuarioRequest $request, string $organizacao_slug): JsonResponse
    {
        try {
            $result = $this->service->store($organizacao_slug, $request->validated());
            return response()->json([
                'data'             => $result['usuario'],
                'senha_temporaria' => $result['senha_temporaria'],
            ], 201);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'TENANT_ERROR'], 422);
        }
    }

    public function update(UpdateTenantUsuarioRequest $request, string $organizacao_slug, int $usuario_id): JsonResponse
    {
        try {
            $usuario = $this->service->update($organizacao_slug, $usuario_id, $request->validated());
            return response()->json(['data' => $usuario]);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function destroy(string $organizacao_slug, int $usuario_id): JsonResponse
    {
        try {
            $this->service->destroy($organizacao_slug, $usuario_id);
            return response()->json(['message' => 'Usuário removido com sucesso.']);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function resetSenha(string $organizacao_slug, int $usuario_id): JsonResponse
    {
        try {
            $novaSenha = $this->service->resetSenha($organizacao_slug, $usuario_id);
            return response()->json([
                'message'          => 'Senha redefinida com sucesso.',
                'senha_temporaria' => $novaSenha,
            ]);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function toggleAtivo(string $organizacao_slug, int $usuario_id): JsonResponse
    {
        try {
            $usuario = $this->service->toggleAtivo($organizacao_slug, $usuario_id);
            return response()->json(['data' => $usuario]);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    // ── Helpers de erro ───────────────────────────────────────────────────────

    private function tenantNotFound(string $slug): JsonResponse
    {
        return response()->json([
            'message' => "Instância do tenant '{$slug}' não encontrada na plataforma.",
            'code'    => 'TENANT_NOT_FOUND',
        ], 404);
    }

    private function tenantDbUnavailable(\Throwable $e): JsonResponse
    {
        $msg = $e->getMessage();

        // Distingue schema ausente (migrations não rodaram) de conexão recusada
        $isSchemaError = stripos($msg, 'relation') !== false
            || stripos($msg, 'does not exist') !== false
            || stripos($msg, 'table') !== false;

        $message = $isSchemaError
            ? 'O banco do tenant existe mas as migrations ainda não foram executadas. Reconstrua o container platform-backend com docker-cli e recrie a organização.'
            : 'O banco de dados do tenant não está acessível. Verifique o status do provisionamento.';

        return response()->json([
            'message' => $message,
            'code'    => 'TENANT_DB_UNAVAILABLE',
            'detail'  => app()->isLocal() ? $msg : null,
        ], 503);
    }
}
