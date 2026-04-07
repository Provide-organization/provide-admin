<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'nome'              => $this->pessoa?->nome_completo ?? $this->email,
            'cpf'               => $this->pessoa?->cpf,
            'email'             => $this->email,
            'role'              => $this->perfis->min('nivel') ?? 5,
            'ativo'             => $this->ativo,
            'deve_trocar_senha' => $this->deve_trocar_senha,
            'criado_em'         => $this->created_at?->toDateString(),
        ];
    }
}
