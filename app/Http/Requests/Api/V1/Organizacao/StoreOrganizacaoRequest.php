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

            // ── Usuário principal da organização (opcional) ──────────────────
            // Quando presente, o backend cria o usuário admin_municipio dentro
            // do banco do tenant após o provisionamento. Senha temporária é
            // retornada no response (exibida uma única vez).
            'admin_inicial'             => ['nullable', 'array'],
            'admin_inicial.nome'        => ['required_with:admin_inicial', 'string', 'max:150'],
            'admin_inicial.email'       => ['required_with:admin_inicial', 'email', 'max:150'],
            'admin_inicial.cpf'         => ['nullable', 'string', 'max:14'],
            'admin_inicial.senha_temp'  => ['nullable', 'string', 'min:8'],
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

            'admin_inicial.nome.required_with'  => 'O nome do admin inicial é obrigatório.',
            'admin_inicial.email.required_with' => 'O e-mail do admin inicial é obrigatório.',
            'admin_inicial.email.email'         => 'E-mail do admin inicial inválido.',
            'admin_inicial.senha_temp.min'      => 'A senha temporária deve ter ao menos 8 caracteres.',
        ];
    }
}
