<?php

namespace App\Services;

use App\Models\Abrigo;
use Illuminate\Database\Eloquent\Collection;

class AbrigoService
{
    public function index(): Collection
    {
        return Abrigo::with(['cidade', 'gestor'])->orderBy('nome')->get();
    }

    public function store(array $data): Abrigo
    {
        return Abrigo::create($data);
    }

    public function update(Abrigo $abrigo, array $data): Abrigo
    {
        $abrigo->update($data);
        return $abrigo->fresh(['cidade', 'gestor']);
    }

    public function destroy(Abrigo $abrigo): void
    {
        // Limpar possíveis relações de ocupação ativas não fazemos no softdelete, 
        // ou precisaria desativar, mas por padrão soft deletes não ativam cascata.
        $abrigo->delete();
    }
}
