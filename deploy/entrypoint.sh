#!/bin/sh

# Adiciona www-data ao grupo que possui o docker.sock (GID varia por host)
if [ -S /var/run/docker.sock ]; then
    SOCK_GID=$(stat -c '%g' /var/run/docker.sock)
    if [ "$SOCK_GID" != "0" ]; then
        addgroup -g "$SOCK_GID" dockerhost 2>/dev/null || true
        addgroup www-data dockerhost 2>/dev/null || true
    else
        addgroup www-data root 2>/dev/null || true
    fi
fi

exec "$@"
