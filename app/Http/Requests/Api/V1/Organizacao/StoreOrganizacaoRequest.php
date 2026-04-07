<?php

namespace App\Http\Requests\Api\V1\Organizacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'     => ['required', 'string', 'max:100'],
            'slug'     => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('organizacoes', 'slug')->whereNull('deleted_at')],
            'cnpj'     => ['nullable', 'string', 'max:18', Rule::unique('organizacoes', 'cnpj')->whereNull('deleted_at')],
            'telefone' => ['nullable', 'string', 'max:20'],
            'ativo'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required'  => 'O nome da organização é obrigatório.',
            'slug.required'  => 'O slug é obrigatório.',
            'slug.unique'    => 'Já existe uma organização com esse slug.',
            'slug.regex'     => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'cnpj.unique'    => 'Já existe uma organização com esse CNPJ.',
        ];
    }
}
