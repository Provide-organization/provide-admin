<?php

namespace App\Exceptions;

use RuntimeException;

/** Conta existe mas está desativada (login/refresh/middleware). */
class InactiveAccountException extends RuntimeException
{
    public function __construct(string $message = 'Conta desativada.')
    {
        parent::__construct($message);
    }
}
