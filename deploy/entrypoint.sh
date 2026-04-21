#!/bin/sh
set -e

# ─── Permissão do Docker socket ───────────────────────────────────────────────
# admin-backend usa `docker exec` no container da instância para rodar
# migrate/seed ao provisionar um novo tenant.
if [ -S /var/run/docker.sock ]; then
    SOCK_GID=$(stat -c '%g' /var/run/docker.sock)
    if [ "$SOCK_GID" != "0" ]; then
        addgroup -g "$SOCK_GID" dockerhost 2>/dev/null || true
        addgroup www-data dockerhost 2>/dev/null || true
    else
        addgroup www-data root 2>/dev/null || true
    fi
fi

# ─── Chaves RS256 compartilhadas ──────────────────────────────────────────────
# Par único por ambiente (dev), gerado aqui na primeira subida e lido também
# pelo instancia-backend via bind mount ./deploy/keys. O issuer do token é o
# slug da org (definido no payload), não na chave.
KEYS_DIR=/var/www/html/storage/keys
mkdir -p "$KEYS_DIR"
chown -R www-data:www-data "$KEYS_DIR"

if [ ! -f "$KEYS_DIR/jwt-private.pem" ] || [ ! -f "$KEYS_DIR/jwt-kid.txt" ]; then
    echo "[entrypoint] Gerando chaves RS256 compartilhadas…"
    su-exec www-data php /var/www/html/artisan jwt:keys:ensure || {
        echo "[entrypoint] jwt:keys:ensure falhou — vendor/ ou DB podem não estar prontos. Continuando."
    }
fi

if [ -f "$KEYS_DIR/jwt-kid.txt" ]; then
    export JWT_KID="$(cat $KEYS_DIR/jwt-kid.txt)"
fi
: "${JWT_ISSUER:=platform}"
export JWT_ISSUER

exec "$@"
