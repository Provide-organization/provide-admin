<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Abrigo\StoreAbrigoRequest;
use App\Http\Requests\Api\V1\Abrigo\UpdateAbrigoRequest;
use App\Http\Resources\Api\V1\AbrigoResource;
use App\Models\Abrigo;
use App\Services\AbrigoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AbrigoController extends Controller
{
    public function __construct(private readonly AbrigoService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return AbrigoResource::collection($this->service->index());
    }

    public function store(StoreAbrigoRequest $request): JsonResponse
    {
        $abrigo = $this->service->store($request->validated());

        return response()->json(
            ['data' => clone new AbrigoResource($abrigo->load(['cidade', 'gestor']))],
            201
        );
    }

    public function show(Abrigo $abrigo): JsonResponse
    {
        $abrigo->load(['cidade', 'gestor']);
        return response()->json(['data' => new AbrigoResource($abrigo)]);
    }

    public function update(UpdateAbrigoRequest $request, Abrigo $abrigo): JsonResponse
    {
        $abrigo = $this->service->update($abrigo, $request->validated());

        return response()->json(['data' => new AbrigoResource($abrigo)]);
    }

    public function destroy(Abrigo $abrigo): JsonResponse
    {
        $this->service->destroy($abrigo);

        return response()->json(['message' => 'Abrigo removido com sucesso.']);
    }
}
