<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UsuarioPerfil extends Pivot
{
    protected $table = 'usuario_perfis';

    public $incrementing = true;

    protected $fillable = [
        'usuario_id',
        'perfil_id',
        'tipo_escopo',
        'escopo_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }
}
