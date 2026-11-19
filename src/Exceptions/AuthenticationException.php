<?php

namespace Prooffice\IdentiqEkyc\Exceptions;

/**
 * Missing, expired or revoked token, or the token request was refused (401).
 */
class AuthenticationException extends IdentiqException
{
}
