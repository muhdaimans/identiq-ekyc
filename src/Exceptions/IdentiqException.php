<?php

namespace Muhdaimans\IdentiqEkyc\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Any error answered by the IDentiQ API. The body follows the API's Error
 * schema: error, message, reason, errors.
 */
class IdentiqException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $errorCode = null,
        public readonly ?string $reason = null,
        public readonly array $errors = [],
        public readonly ?int $retryAfter = null,
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $errorCode = isset($body['error']) && is_string($body['error']) ? $body['error'] : null;
        $message = isset($body['message']) && is_string($body['message'])
            ? $body['message']
            : ($errorCode ?? 'IDentiQ request failed with HTTP '.$response->status());

        $retryAfter = $response->header('Retry-After');
        $retryAfter = is_numeric($retryAfter) ? (int) $retryAfter : null;

        $class = match (true) {
            $response->status() === 401 => AuthenticationException::class,
            $response->status() === 403 => AuthorizationException::class,
            $response->status() === 404 => NotFoundException::class,
            $response->status() === 409 => ConflictException::class,
            $response->status() === 422 => ValidationException::class,
            $response->status() === 423 => OnboardingSuspendedException::class,
            $response->status() === 429 => RateLimitedException::class,
            $response->serverError() => ServerException::class,
            default => self::class,
        };

        return new $class(
            message: $message,
            status: $response->status(),
            errorCode: $errorCode,
            reason: isset($body['reason']) && is_string($body['reason']) ? $body['reason'] : null,
            errors: isset($body['errors']) && is_array($body['errors']) ? $body['errors'] : [],
            retryAfter: $retryAfter,
            response: $response,
        );
    }
}
