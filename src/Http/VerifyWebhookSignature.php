<?php

namespace Muhdaimans\IdentiqEkyc\Http;

use Closure;
use Illuminate\Http\Request;
use Muhdaimans\IdentiqEkyc\Exceptions\InvalidWebhookSignature;
use Muhdaimans\IdentiqEkyc\Webhooks\SignatureVerifier;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function __construct(private readonly SignatureVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->verifier->verify(
                $request->getContent(),
                $request->header('X-Ekyc-Timestamp'),
                [$request->header('X-Ekyc-Signature'), $request->header('X-Ekyc-Signature-Previous')],
            );
        } catch (InvalidWebhookSignature $e) {
            return response()->json(['error' => 'invalid_signature', 'message' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}
