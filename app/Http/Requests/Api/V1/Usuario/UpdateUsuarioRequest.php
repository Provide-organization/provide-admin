<?php

namespace App\Http\Requests\Api\V1\Usuario;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsuarioRequest extends FormRequest
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
            'ativo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.min' => 'Nível de acesso inválido.',
            'role.max' => 'Nível de acesso inválido.',
        ];
    }
}
