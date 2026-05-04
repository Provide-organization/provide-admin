<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $connection = 'platform_logs';

    protected $fillable = [
        'usuario_id', 'metodo',
        'url', 'rota',
        'payload', 'resposta', 'status_code',
        'duracao_ms', 'ip',
    ];

    protected $casts = [
        'payload'  => 'array',
        'resposta' => 'array',
    ];
}
