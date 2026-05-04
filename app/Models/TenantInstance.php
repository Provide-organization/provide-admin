<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInstance extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_instances';

    protected $fillable = [
        'organizacao_id',
        'slug',
        'container_name',
        'db_name',
        'db_logs_name',
        'db_username',
        'status',
        'provisioned_at',
    ];

    protected $casts = [
        'provisioned_at' => 'datetime',
    ];

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class, 'organizacao_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
