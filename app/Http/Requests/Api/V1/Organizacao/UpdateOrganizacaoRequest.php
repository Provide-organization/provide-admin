<?php

namespace App\Http\Requests\Api\V1\Organizacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizacao = $this->route('organizacao');
        $id = $organizacao instanceof \App\Models\Organizacao ? $organizacao->id : $organizacao;

        return [
            'nome'     => ['sometimes', 'string', 'max:100'],
            'cnpj'     => ['nullable', 'string', 'max:18', Rule::unique('organizacoes', 'cnpj')->ignore($id)],
            'telefone' => ['nullable', 'string', 'max:20'],
            'ativo'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.unique' => 'Já existe uma organização com esse CNPJ.',
        ];
    }
}
