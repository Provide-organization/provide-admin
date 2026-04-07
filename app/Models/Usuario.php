<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    use SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'email',
        'senha',
        'ativo',
        'deve_trocar_senha',
    ];

    protected $hidden = [
        'senha',
    ];

    protected $casts = [
        'ativo'             => 'boolean',
        'deve_trocar_senha' => 'boolean',
    ];

    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function pessoa(): HasOne
    {
        return $this->hasOne(Pessoa::class, 'usuario_id');
    }

    public function usuarioPerfis(): HasMany
    {
        return $this->hasMany(UsuarioPerfil::class, 'usuario_id');
    }

    public function perfis(): BelongsToMany
    {
        return $this->belongsToMany(Perfil::class, 'usuario_perfis', 'usuario_id', 'perfil_id')
                    ->withPivot('tipo_escopo', 'escopo_id')
                    ->withTimestamps();
    }
}
