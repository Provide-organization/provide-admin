<?php
namespace App\Http\Middleware;

use App\Models\EventLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogEventos
{
    private array $rotasIgnoradas = [
        'api/v1/auth/me',
        'api/v1/audit-logs',
        'api/v1/event-logs',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);

        $response = $next($request);

        // Ignora GETs simples
        if ($request->isMethod('GET')) {
            return $response;
        }

        foreach ($this->rotasIgnoradas as $rota) {
            if ($request->is($rota)) {
                return $response;
            }
        }

        $duracao = (int) ((microtime(true) - $inicio) * 1000);

        EventLog::create([
            'usuario_id'  => Auth::id(),
            'metodo'      => $request->method(),
            'url'         => $request->fullUrl(),
            'rota'        => $request->route()?->getName(),
            'payload'     => $this->sanitizarPayload($request->except(['senha', 'password'])),
            'resposta'    => $this->extrairResposta($response),
            'status_code' => $response->getStatusCode(),
            'duracao_ms'  => $duracao,
            'ip'          => $request->ip(),
        ]);

        return $response;
    }

    private function sanitizarPayload(array $dados): array
    {
        return array_diff_key($dados, array_flip(['senha', 'password', 'token']));
    }

    private function extrairResposta(Response $response): ?array
    {
        $content = $response->getContent();
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
