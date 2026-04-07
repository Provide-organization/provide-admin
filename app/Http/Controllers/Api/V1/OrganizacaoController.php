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

    public function destroy(Organizacao $organizacao): JsonResponse
    {
        $this->service->destroy($organizacao);

        return response()->json(['message' => 'Organização removida com sucesso.']);
    }
}
