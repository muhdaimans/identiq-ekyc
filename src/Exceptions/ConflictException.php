<?php

namespace Prooffice\IdentiqEkyc\Exceptions;

/**
 * 409: the journey is final (journey_terminal), or the first request with the
 * same Idempotency-Key has not finished yet.
 */
class ConflictException extends IdentiqException
{
    public function isJourneyTerminal(): bool
    {
        return $this->errorCode === 'journey_terminal';
    }

    public function isIdempotencyInProgress(): bool
    {
        return ! $this->isJourneyTerminal();
    }
}
