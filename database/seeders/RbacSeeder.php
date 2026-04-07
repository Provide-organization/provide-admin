<?php

namespace Database\Seeders;

use App\Models\Perfil;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        Perfil::firstOrCreate(
            ['nome' => 'admin_plataforma'],
            [
                'nome'     => 'admin_plataforma',
                'nivel'    => 1,
                'descricao' => 'Acesso total à plataforma — gerenciado pela PROVIDE',
            ]
        );
    }
}
