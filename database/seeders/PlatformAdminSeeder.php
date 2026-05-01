<?php

namespace Database\Seeders;

use App\Models\Perfil;
use App\Models\Pessoa;
use App\Models\Usuario;
use App\Models\UsuarioPerfil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $perfil = Perfil::where('nome', 'admin_plataforma')->firstOrFail();

        $admin = Usuario::updateOrCreate(
            ['email' => 'admin@provide.app'],
            [
                'email'             => 'admin@provide.app',
                'senha'             => Hash::make('Provide@2026'),
                'ativo'             => true,
                'deve_trocar_senha' => false,
            ]
        );

        Pessoa::firstOrCreate(
            ['usuario_id' => $admin->id],
            [
                'nome_completo' => 'Administrador PROVIDE',
                'usuario_id'    => $admin->id,
            ]
        );

        UsuarioPerfil::firstOrCreate(
            ['usuario_id' => $admin->id, 'perfil_id' => $perfil->id],
            [
                'usuario_id'  => $admin->id,
                'perfil_id'   => $perfil->id,
                'tipo_escopo' => null,
                'escopo_id'   => null,
            ]
        );
    }
}
