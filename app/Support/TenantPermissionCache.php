<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Alinhado a {@see \App\Services\PermissionResolver} na instância (prefixo perms:u: + database + user id).
 * Usado pelo admin para invalidar cache quando altera dados do tenant por SQL direto.
 */
final class TenantPermissionCache
{
    public static function forgetForUserOnConnection(string $connectionName, int $userId): void
    {
        $db = (string) config("database.connections.{$connectionName}.database");
        if ($db === '') {
            return;
        }

        try {
            Cache::forget('perms:u:' . $db . ':' . $userId);
        } catch (\Throwable) {
            // best-effort (admin pode não partilhar store com a instância)
        }
    }
}
