<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PlatformKey extends Model
{
    protected $table = 'platform_keys';

    public const OWNER_PLATFORM    = 'platform';
    public const OWNER_ORGANIZACAO = 'organizacao';

    public const ALGO_RS256 = 'RS256';

    public $timestamps = false;

    protected $fillable = [
        'owner_type',
        'organizacao_id',
        'kid',
        'issuer',
        'algorithm',
        'public_key',
        'private_key_enc',
        'created_at',
        'revoked_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class, 'organizacao_id');
    }

    /**
     * Devolve a chave privada em PEM decifrada, ou null se não armazenada.
     */
    public function getPrivateKey(): ?string
    {
        if (! $this->private_key_enc) {
            return null;
        }
        return Crypt::decrypt($this->private_key_enc);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
