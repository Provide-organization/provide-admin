<?php

namespace App\Http\Requests\Api\V1\TenantUsuario;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'             => ['required', 'string', 'max:255'],
            'cpf'              => ['nullable', 'string', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'],
            'email'            => ['required', 'email', 'max:255'],
            'role'             => ['required', 'integer', 'min:2', 'max:5'],
            'senha_temporaria' => ['nullable', 'string', 'min:6', 'max:100'],
            'ativo'            => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required'  => 'O nome completo é obrigatório.',
            'cpf.regex'      => 'CPF inválido. Use o formato 000.000.000-00.',
            'email.required' => 'O e-mail institucional é obrigatório.',
            'email.email'    => 'Informe um e-mail válido.',
            'role.required'  => 'O nível de acesso é obrigatório.',
            'role.min'       => 'Nível de acesso inválido para usuário municipal (mínimo: 2).',
            'role.max'       => 'Nível de acesso inválido (máximo: 5).',
        ];
    }
}
