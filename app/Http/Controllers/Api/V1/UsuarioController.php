<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Usuario\StoreUsuarioRequest;
use App\Http\Requests\Api\V1\Usuario\UpdateUsuarioRequest;
use App\Http\Resources\Api\V1\UsuarioResource;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return UsuarioResource::collection($this->service->index());
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());

        return response()->json([
            'data'             => new UsuarioResource($result['usuario']),
            'senha_temporaria' => $result['senha_temporaria'],
        ], 201);
    }

    public function show(Usuario $usuario): JsonResponse
    {
        $usuario->load(['pessoa', 'perfis']);
        return response()->json(['data' => new UsuarioResource($usuario)]);
    }

    public function update(UpdateUsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $usuario = $this->service->update($usuario, $request->validated());
        return response()->json(['data' => new UsuarioResource($usuario)]);
    }

    public function destroy(Usuario $usuario): JsonResponse
    {
        if ($usuario->id === auth()->id()) {
            return response()->json([
                'message' => 'Você não pode excluir a própria conta.',
                'code'    => 'SELF_DELETE_FORBIDDEN',
            ], 422);
        }

        $this->service->destroy($usuario);
        return response()->json(['message' => 'Usuário removido com sucesso.']);
    }

    public function resetSenha(Usuario $usuario): JsonResponse
    {
        $novaSenha = $this->service->resetSenha($usuario);
        return response()->json([
            'message'          => 'Senha redefinida com sucesso.',
            'senha_temporaria' => $novaSenha,
        ]);
    }
}
