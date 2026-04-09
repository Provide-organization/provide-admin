<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $instance = $this->tenantInstance;

        return [
            'id'       => $this->id,
            'nome'     => $this->nome,
            'slug'     => $this->slug,
            'cnpj'     => $this->cnpj,
            'telefone'     => $this->telefone,
            'ativo'        => $this->ativo,
            'criado_em'    => $this->created_at?->toDateString(),
            'tenant'     => $instance ? [
                'status'         => $instance->status,
                'container_name' => $instance->container_name,
                'db_name'        => $instance->db_name,
                'provisioned_at' => $instance->provisioned_at?->toDateTimeString(),
            ] : null,
        ];
    }
}
