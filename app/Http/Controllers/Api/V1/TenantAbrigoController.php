<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TenantAbrigoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantAbrigoController extends Controller
{
    public function __construct(private readonly TenantAbrigoService $service) {}

    public function index(string $organizacao_slug): JsonResponse
    {
        try {
            $abrigos = $this->service->index($organizacao_slug);
            return response()->json(['data' => $abrigos]);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function store(Request $request, string $organizacao_slug): JsonResponse
    {
        $data = $request->validate([
            'nome'              => 'required|string|max:255',
            'cep'               => 'nullable|string|max:9',
            'telefone'          => 'nullable|string|max:20',
            'cidade'            => 'nullable|string|max:100',
            'capacidade_atual'  => 'required|integer|min:0',
            'capacidade_maxima' => 'required|integer|min:1',
            'status'            => 'nullable|boolean',
            'gestor_id'         => 'nullable|integer',
        ]);

        try {
            $abrigo = $this->service->store($organizacao_slug, $data);
            return response()->json(['data' => $abrigo], 201);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'TENANT_ERROR'], 422);
        }
    }

    public function update(Request $request, string $organizacao_slug, int $abrigo_id): JsonResponse
    {
        $data = $request->validate([
            'nome'              => 'nullable|string|max:255',
            'cep'               => 'nullable|string|max:9',
            'telefone'          => 'nullable|string|max:20',
            'cidade'            => 'nullable|string|max:100',
            'capacidade_atual'  => 'nullable|integer|min:0',
            'capacidade_maxima' => 'nullable|integer|min:1',
            'status'            => 'nullable|boolean',
            'gestor_id'         => 'nullable|integer',
        ]);

        try {
            $abrigo = $this->service->update($organizacao_slug, $abrigo_id, $data);
            return response()->json(['data' => $abrigo]);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'TENANT_ERROR'], 404);
        }
    }

    public function destroy(string $organizacao_slug, int $abrigo_id): JsonResponse
    {
        try {
            $this->service->destroy($organizacao_slug, $abrigo_id);
            return response()->json(['message' => 'Abrigo removido com sucesso.']);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        }
    }

    public function toggleStatus(string $organizacao_slug, int $abrigo_id): JsonResponse
    {
        try {
            $abrigo = $this->service->toggleStatus($organizacao_slug, $abrigo_id);
            return response()->json(['data' => $abrigo]);
        } catch (ModelNotFoundException) {
            return $this->tenantNotFound($organizacao_slug);
        } catch (QueryException|\PDOException $e) {
            return $this->tenantDbUnavailable($e);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'TENANT_ERROR'], 404);
        }
    }

    // ── Helpers de erro ───────────────────────────────────────────────────────

    private function tenantNotFound(string $slug): JsonResponse
    {
        return response()->json([
            'message' => "Instância da organização '{$slug}' não encontrada na plataforma.",
            'code'    => 'TENANT_NOT_FOUND',
        ], 404);
    }

    private function tenantDbUnavailable(\Throwable $e): JsonResponse
    {
        $msg = $e->getMessage();

        $isSchemaError = stripos($msg, 'relation') !== false
            || stripos($msg, 'does not exist') !== false
            || stripos($msg, 'table') !== false;

        $message = $isSchemaError
            ? 'O banco da organização existe mas as migrations ainda não foram executadas.'
            : 'O banco de dados da organização não está acessível.';

        return response()->json([
            'message' => $message,
            'code'    => 'TENANT_DB_UNAVAILABLE',
            'detail'  => app()->isLocal() ? $msg : null,
        ], 503);
    }
}
