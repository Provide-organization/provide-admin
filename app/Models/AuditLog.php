<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $connection = 'platform_logs';

    protected $fillable = [
        'usuario_id', 'usuario_nome', 'acao',
        'modelo', 'modelo_id',
        'payload_antes', 'payload_depois', 'ip',
    ];

    protected $casts = [
        'payload_antes'  => 'array',
        'payload_depois' => 'array',
    ];
}
