<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'senha_atual' => ['required', 'string'],
            'senha_nova'  => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'senha_atual.required'    => 'Informe a senha atual.',
            'senha_nova.required'     => 'Informe a nova senha.',
            'senha_nova.confirmed'    => 'A confirmação da nova senha não confere.',
            'senha_nova.min'          => 'A nova senha deve ter ao menos 8 caracteres.',
        ];
    }
}
