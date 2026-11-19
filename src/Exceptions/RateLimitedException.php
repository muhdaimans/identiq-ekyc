<?php

namespace Prooffice\IdentiqEkyc\Exceptions;

/**
 * Per-IP or per-client rate limit exceeded (429); see $retryAfter.
 */
class RateLimitedException extends IdentiqException
{
}
