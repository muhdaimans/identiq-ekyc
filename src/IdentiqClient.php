<?php

namespace Muhdaimans\IdentiqEkyc;

use DateTimeInterface;
use InvalidArgumentException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Muhdaimans\IdentiqEkyc\Auth\TokenProvider;
use Muhdaimans\IdentiqEkyc\Data\IssuedLink;
use Muhdaimans\IdentiqEkyc\Data\Journey;
use Muhdaimans\IdentiqEkyc\Data\JourneyPage;
use Muhdaimans\IdentiqEkyc\Data\LinkRequest;
use Muhdaimans\IdentiqEkyc\Data\OnboardingRequest;
use Muhdaimans\IdentiqEkyc\Exceptions\ConflictException;
use Muhdaimans\IdentiqEkyc\Exceptions\ConnectionException;
use Muhdaimans\IdentiqEkyc\Exceptions\IdentiqException;
use Muhdaimans\IdentiqEkyc\Exceptions\RateLimitedException;
use Muhdaimans\IdentiqEkyc\Exceptions\ServerException;

class IdentiqClient
{
    public function __construct(
        private readonly Http $http,
        private readonly TokenProvider $tokens,
        private readonly IdentiqConfig $config,
    ) {}

    /**
     * Create a journey and its one-time capture link. Scope onboarding:write.
     *
     * Pass your own $idempotencyKey (for example derived from your applicant id)
     * to make the call safe to repeat across processes; otherwise a UUID is used
     * and reused for this call's own retries only.
     *
     * @param  OnboardingRequest|array<string, mixed>  $request
     */
    public function createOnboarding(OnboardingRequest|array $request, ?string $idempotencyKey = null): IssuedLink
    {
        $key = $this->idempotencyKey($idempotencyKey);
        $body = $request instanceof OnboardingRequest ? $request->toArray() : $request;

        $response = $this->send('POST', '/v1/onboarding', ['json' => $body], $key);

        return IssuedLink::fromArray($this->json($response), $key, $this->replayed($response));
    }

    /**
     * Issue a fresh capture link for a journey that is not finished. Scope onboarding:write.
     * Throws ConflictException (isJourneyTerminal()) once the journey is final.
     *
     * @param  LinkRequest|array<string, mixed>  $request
     */
    public function reissueLink(string $ref, LinkRequest|array $request = [], ?string $idempotencyKey = null): IssuedLink
    {
        $key = $this->idempotencyKey($idempotencyKey);
        $body = $request instanceof LinkRequest ? $request->toArray() : $request;
        $options = $body === [] ? [] : ['json' => $body];

        $response = $this->send('POST', '/v1/journeys/'.rawurlencode($ref).'/links', $options, $key);

        return IssuedLink::fromArray($this->json($response), $key, $this->replayed($response));
    }

    /**
     * State, decision and normalised result of one journey. Scope results:read.
     */
    public function journey(string $ref): Journey
    {
        return Journey::fromArray($this->json($this->send('GET', '/v1/journeys/'.rawurlencode($ref))));
    }

    /**
     * One page of the reconciliation feed. Scope results:read.
     */
    public function journeys(?DateTimeInterface $updatedSince = null, int $perPage = 50, ?string $cursor = null): JourneyPage
    {
        $query = array_filter([
            'updated_since' => $updatedSince?->format(DateTimeInterface::RFC3339),
            'per_page' => $perPage,
            'cursor' => $cursor,
        ], fn ($value) => $value !== null);

        return JourneyPage::fromArray($this->json($this->send('GET', '/v1/journeys', ['query' => $query])));
    }

    /**
     * Every journey changed since $updatedSince, following the cursor lazily.
     *
     * @return LazyCollection<int, Journey>
     */
    public function allJourneys(?DateTimeInterface $updatedSince = null, int $perPage = 100): LazyCollection
    {
        return LazyCollection::make(function () use ($updatedSince, $perPage) {
            $cursor = null;

            do {
                $page = $this->journeys($updatedSince, $perPage, $cursor);

                foreach ($page->journeys as $journey) {
                    yield $journey;
                }

                $cursor = $page->nextCursor;
            } while ($page->hasMore());
        });
    }

    /**
     * PNG bytes (300 px) of the QR code for the journey's latest capture link. Scope onboarding:write.
     */
    public function qrCode(string $ref): string
    {
        return $this->send('GET', '/v1/journeys/'.rawurlencode($ref).'/qr.png', accept: 'image/png')->body();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function send(string $method, string $uri, array $options = [], ?string $idempotencyKey = null, string $accept = 'application/json'): Response
    {
        $attempt = 0;
        $refreshedToken = false;

        while (true) {
            $attempt++;

            try {
                $response = $this->request($idempotencyKey, $accept)->send($method, $uri, $options);
            } catch (HttpConnectionException $e) {
                // GETs are safe, and POSTs always carry an Idempotency-Key, so a retry never duplicates a write.
                if ($attempt <= $this->config->retryTimes) {
                    $this->pause($attempt, null);

                    continue;
                }

                throw new ConnectionException('Could not reach IDentiQ: '.$e->getMessage(), previous: $e);
            }

            if ($response->successful()) {
                return $response;
            }

            if ($response->status() === 401 && ! $refreshedToken) {
                $this->tokens->forget();
                $refreshedToken = true;
                $attempt--;

                continue;
            }

            $exception = IdentiqException::fromResponse($response);

            if ($this->isRetryable($exception) && $attempt <= $this->config->retryTimes
                && ($exception->retryAfter === null || $exception->retryAfter <= $this->config->retryMaxWait)) {
                $this->pause($attempt, $exception->retryAfter);

                continue;
            }

            throw $exception;
        }
    }

    protected function request(?string $idempotencyKey, string $accept): PendingRequest
    {
        $request = $this->http
            ->baseUrl($this->config->baseUrl)
            ->withOptions($this->config->httpOptions)
            ->withToken($this->tokens->token())
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout)
            ->accept($accept);

        return $idempotencyKey !== null
            ? $request->withHeaders(['Idempotency-Key' => $idempotencyKey])
            : $request;
    }

    private function isRetryable(IdentiqException $exception): bool
    {
        return $exception instanceof RateLimitedException
            || $exception instanceof ServerException
            || ($exception instanceof ConflictException && $exception->isIdempotencyInProgress());
    }

    private function pause(int $attempt, ?int $retryAfter): void
    {
        $seconds = $retryAfter ?? min(2 ** ($attempt - 1), max(1, $this->config->retryMaxWait));

        Sleep::for(max(0, $seconds))->seconds();
    }

    private function idempotencyKey(?string $key): string
    {
        $key ??= (string) Str::uuid();

        if ($key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('Idempotency-Key must be 1 to 128 characters.');
        }

        return $key;
    }

    private function replayed(Response $response): bool
    {
        return strtolower($response->header('Idempotent-Replayed')) === 'true';
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
