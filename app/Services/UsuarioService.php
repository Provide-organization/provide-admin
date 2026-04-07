<?php

namespace App\Services;

use App\Models\Perfil;
use App\Models\Pessoa;
use App\Models\Usuario;
use App\Models\UsuarioPerfil;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsuarioService
{
    public function index(): Collection
    {
        return Usuario::with(['pessoa', 'perfis'])
            ->orderBy('id')
            ->get();
    }

    public function store(array $data): array
    {
        Usuario::withTrashed()
            ->whereNotNull('deleted_at')
            ->where('email', $data['email'])
            ->forceDelete();

        if (!empty($data['cpf'])) {
            Pessoa::withTrashed()
                ->whereNotNull('deleted_at')
                ->where('cpf', $data['cpf'])
                ->forceDelete();
        }

        $senhaPlain = $data['senha_temporaria'] ?? $this->gerarSenha();

        $usuario = Usuario::create([
            'email'             => $data['email'],
            'senha'             => Hash::make($senhaPlain),
            'ativo'             => $data['ativo'] ?? true,
            'deve_trocar_senha' => true,
        ]);

        Pessoa::create([
            'nome_completo' => $data['nome'],
            'cpf'           => !empty($data['cpf']) ? $data['cpf'] : null,
            'usuario_id'    => $usuario->id,
        ]);

        $perfil = Perfil::where('nivel', $data['role'] ?? 1)->firstOrFail();

        UsuarioPerfil::create([
            'usuario_id'  => $usuario->id,
            'perfil_id'   => $perfil->id,
            'tipo_escopo' => null,
            'escopo_id'   => null,
        ]);

        $usuario->load(['pessoa', 'perfis']);

        return [
            'usuario'          => $usuario,
            'senha_temporaria' => $senhaPlain,
        ];
    }

    public function update(Usuario $usuario, array $data): Usuario
    {
        if (isset($data['nome'])) {
            if ($usuario->pessoa) {
                $usuario->pessoa->update(['nome_completo' => $data['nome']]);
            } else {
                Pessoa::create(['nome_completo' => $data['nome'], 'usuario_id' => $usuario->id]);
            }
        }

        if (isset($data['role'])) {
            $perfil = Perfil::where('nivel', $data['role'])->firstOrFail();
            UsuarioPerfil::where('usuario_id', $usuario->id)->delete();
            UsuarioPerfil::create([
                'usuario_id'  => $usuario->id,
                'perfil_id'   => $perfil->id,
                'tipo_escopo' => null,
                'escopo_id'   => null,
            ]);
        }

        if (isset($data['ativo'])) {
            $usuario->update(['ativo' => $data['ativo']]);
        }

        return $usuario->fresh(['pessoa', 'perfis']);
    }

    public function destroy(Usuario $usuario): void
    {
        $usuario->perfis()->detach();
        $usuario->delete();
    }

    public function resetSenha(Usuario $usuario): string
    {
        $novaSenha = $this->gerarSenha();

        $usuario->update([
            'senha'             => Hash::make($novaSenha),
            'deve_trocar_senha' => true,
        ]);

        return $novaSenha;
    }

    private function gerarSenha(): string
    {
        return 'Provide@' . strtoupper(Str::random(4));
    }
}
