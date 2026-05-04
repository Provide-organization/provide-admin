<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $connection = 'platform_logs';

    protected $fillable = [
        'tipo', 'mensagem', 'arquivo',
        'linha', 'stack_trace',
        'url', 'metodo', 'ip', 'usuario_id',
    ];
}
