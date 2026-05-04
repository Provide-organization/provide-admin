<?php
namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditavel
{
    public static function bootAuditavel(): void
    {
        static::created(function ($model) {
            self::registrarAudit('criou', $model, null, $model->toArray());
        });

        static::updated(function ($model) {
            self::registrarAudit('atualizou', $model, $model->getOriginal(), $model->toArray());
        });

        static::deleted(function ($model) {
            self::registrarAudit('deletou', $model, $model->toArray(), null);
        });
    }

    private static function registrarAudit(string $acao, $model, ?array $antes, ?array $depois): void
    {
        if (app()->runningInConsole())
        {
            return;
        }

        // Remove campos sensíveis
        $camposSensiveis = ['senha', 'password', 'remember_token'];
        $antes  = $antes  ? array_diff_key($antes,  array_flip($camposSensiveis)) : null;
        $depois = $depois ? array_diff_key($depois, array_flip($camposSensiveis)) : null;

        AuditLog::create([
            'usuario_id'    => Auth::id(),
            'usuario_nome'  => Auth::user()?->nome ?? 'Sistema',
            'acao'          => $acao,
            'modelo'        => class_basename($model),
            'modelo_id'     => $model->getKey(),
            'payload_antes' => $antes,
            'payload_depois'=> $depois,
            'ip'            => request()->ip(),
        ]);
    }
}
