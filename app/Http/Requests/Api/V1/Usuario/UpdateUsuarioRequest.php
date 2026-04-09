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
            'ativo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
