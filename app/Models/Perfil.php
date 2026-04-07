<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Perfil extends Model
{
    protected $table = 'perfis';

    protected $fillable = [
        'nome',
        'nivel',
        'descricao',
    ];

    protected $casts = [
        'nivel' => 'integer',
    ];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'usuario_perfis',
            'perfil_id',
            'usuario_id'
        )->withPivot('tipo_escopo', 'escopo_id')
        ->withTimestamps();
    }

    public function usuarioPerfis(): HasMany
    {
        return $this->hasMany(UsuarioPerfil::class, 'perfil_id');
    }
}
