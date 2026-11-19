<?php

namespace Prooffice\IdentiqEkyc\Exceptions;

/**
 * 403: missing scope (insufficient_scope), client without a tenant or inactive,
 * or mutual TLS required and not satisfied ($reason is untrusted_edge,
 * certificate_not_verified, fingerprint_mismatch or no_registered_fingerprint).
 */
class AuthorizationException extends IdentiqException
{
    public function isInsufficientScope(): bool
    {
        return $this->errorCode === 'insufficient_scope';
    }
}
