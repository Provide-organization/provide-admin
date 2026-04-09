<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organizacao\StoreOrganizacaoRequest;
use App\Http\Requests\Api\V1\Organizacao\UpdateOrganizacaoRequest;
use App\Http\Resources\Api\V1\OrganizacaoResource;
use App\Models\Organizacao;
use App\Services\OrganizacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrganizacaoController extends Controller
{
    public function __construct(private readonly OrganizacaoService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return OrganizacaoResource::collection($this->service->index());
    }

    public function store(StoreOrganizacaoRequest $request): JsonResponse
    {
        $organizacao = $this->service->store($request->validated());

        return response()->json(
            ['data' => new OrganizacaoResource($organizacao)],
            201
        );
    }

    public function show(Organizacao $organizacao): JsonResponse
    {
        return response()->json(['data' => new OrganizacaoResource($organizacao)]);
    }

    public function update(UpdateOrganizacaoRequest $request, Organizacao $organizacao): JsonResponse
    {
        $organizacao = $this->service->update($organizacao, $request->validated());

        return response()->json(['data' => new OrganizacaoResource($organizacao)]);
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $organizacao = Organizacao::with('tenantInstance')->where('slug', $slug)->firstOrFail();
        return response()->json(['data' => new OrganizacaoResource($organizacao)]);
    }

    /**
     * Endpoint público (sem autenticação) para verificar se uma organização existe e está ativa.
     * Usado pelo middleware Next.js para bloquear acesso a subdomínios inválidos.
     */
    public function check(string $slug): JsonResponse
    {
        $exists = Organizacao::where('slug', $slug)->where('ativo', true)->exists();

        if (! $exists) {
            return response()->json([
                'exists'  => false,
                'message' => "Organização '{$slug}' não encontrada ou inativa.",
            ], 404);
        }

        return response()->json(['exists' => true]);
    }

    public function reprovision(string $slug): JsonResponse
    {
        try {
            $organizacao = Organizacao::with('tenantInstance')->where('slug', $slug)->firstOrFail();

            if (! $organizacao->tenantInstance) {
                return response()->json([
                    'message' => 'Instância de tenant não encontrada para esta organização.',
                    'code'    => 'TENANT_INSTANCE_NOT_FOUND',
                ], 404);
            }

            $this->service->reprovision($organizacao->tenantInstance);

            return response()->json([
                'message' => 'Tenant provisionado com sucesso.',
                'data'    => new OrganizacaoResource($organizacao->fresh('tenantInstance')),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Organização não encontrada.'], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'REPROVISION_FAILED',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha no provisionamento: ' . $e->getMessage(),
                'code'    => 'REPROVISION_ERROR',
            ], 503);
        }
    }

    public function destroy(Organizacao $organizacao): JsonResponse
    {
        $this->service->destroy($organizacao);

        return response()->json(['message' => 'Organização removida com sucesso.']);
    }
}
