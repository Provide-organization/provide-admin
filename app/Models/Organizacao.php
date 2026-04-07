<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organizacao extends Model
{
    use SoftDeletes;

    protected $table = 'organizacoes';

    protected $fillable = [
        'nome',
        'slug',
        'cnpj',
        'telefone',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function tenantInstance(): HasOne
    {
        return $this->hasOne(TenantInstance::class, 'organizacao_id');
    }
}
