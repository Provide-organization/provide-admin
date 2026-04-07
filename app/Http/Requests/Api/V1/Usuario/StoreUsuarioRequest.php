<?php

namespace App\Http\Requests\Api\V1\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'             => ['required', 'string', 'max:255'],
            'cpf'              => ['nullable', 'string', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', Rule::unique('pessoas', 'cpf')->whereNull('deleted_at')],
            'email'            => ['required', 'email', 'max:255', Rule::unique('usuarios', 'email')->whereNull('deleted_at')],
            'role'             => ['integer', 'min:1', 'max:1'],
            'senha_temporaria' => ['nullable', 'string', 'min:6', 'max:100'],
            'ativo'            => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required'  => 'O nome completo é obrigatório.',
            'cpf.regex'      => 'CPF inválido. Use o formato 000.000.000-00.',
            'cpf.unique'     => 'Já existe um usuário com esse CPF.',
            'email.required' => 'O e-mail institucional é obrigatório.',
            'email.email'    => 'Informe um e-mail válido.',
            'email.unique'   => 'Já existe um usuário com esse e-mail.',
        ];
    }
}
