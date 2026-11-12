<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessChapaWebhook;
use App\Services\AuditLogger;
use App\Services\ChapaWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapaWebhookController extends Controller
{
    // Public endpoint: no session, no CSRF token. It is protected by the signature instead.
    public function __invoke(Request $request, ChapaWebhookService $webhook): JsonResponse
    {
        // The RAW body is what was signed, so it is read before anything decodes it.
        $raw = $request->getContent();
        $payloadSignature = $request->header('x-chapa-signature');
        $plainSignature = $request->header('chapa-signature');

        if (! $webhook->isAuthentic($raw, $payloadSignature, $plainSignature)) {
            // PAY-05: rejected and audit-logged (the IP is recorded automatically). The signatures are NOT logged.
            AuditLogger::log('webhook.rejected', null, null, null, [
                'reason' => 'missing_or_invalid_signature',
                'has_payload_header' => $payloadSignature !== null,
                'has_plain_header' => $plainSignature !== null,
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            AuditLogger::log('webhook.ignored', null, null, null, ['reason' => 'invalid_json']);

            return response()->json(['received' => true]);
        }

        $event = $webhook->store($payload);

        if ($event) {
            ProcessChapaWebhook::dispatch($event->id); // verification happens in the queue, so we answer fast
        }

        return response()->json(['received' => true]);
    }
}