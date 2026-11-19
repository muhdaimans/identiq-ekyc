<?php

namespace Muhdaimans\IdentiqEkyc\Exceptions;

/**
 * 422: validation failed ($errors), sandbox_only, idempotency_key_required, or
 * an Idempotency-Key reused with a different request.
 */
class ValidationException extends IdentiqException
{
    public function isSandboxOnly(): bool
    {
        return $this->errorCode === 'sandbox_only';
    }
}
