<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Após autenticação JWT: bloqueia contas com {@see Usuario::$ativo} = false.
 * Logout permanece acessível para permitir limpar cookie mesmo inativo.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('auth.logout')) {
            return $next($request);
        }

        $user = $request->user();
        if ($user instanceof Usuario && ! $user->ativo) {
            return response()->json([
                'message' => 'Conta desativada.',
                'code'    => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
