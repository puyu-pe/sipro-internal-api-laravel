<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Exceptions;

use Exception;
use Throwable;
use PuyuPe\SiproInternalApiCore\Errors\InternalApiError;

class TenantAdapterException extends Exception
{
    public function __construct(
        private readonly InternalApiError $error,
        ?string $message = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message ?? $error->message, $code, $previous);
    }

    public function error(): InternalApiError
    {
        return $this->error;
    }
}
