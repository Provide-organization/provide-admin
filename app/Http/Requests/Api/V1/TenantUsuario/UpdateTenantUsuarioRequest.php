<?php

namespace App\Http\Requests\Api\V1\TenantUsuario;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'  => ['sometimes', 'string', 'max:255'],
            'role'  => ['sometimes', 'integer', 'min:2', 'max:5'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.min' => 'Nível de acesso inválido para usuário municipal (mínimo: 2).',
            'role.max' => 'Nível de acesso inválido (máximo: 5).',
        ];
    }
}
