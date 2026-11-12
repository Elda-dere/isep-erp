<?php

namespace App\Services;

use App\Exceptions\ChapaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * The ONLY class that talks to Chapa over HTTP. It knows nothing about sales: it sends a request and
 * returns the answer, or throws ChapaException with a message that is safe to show.
 * The secret key is read from config at the moment of the call and is never logged, stored or put in a message.
 */
class ChapaClient
{
    // PAY-01: asks Chapa for a hosted checkout link. $payload holds amount, currency, tx_ref, return_url ...
    public function initialize(array $payload): string
    {
        $body = $this->send('post', '/v1/transaction/initialize', $payload, 'start the payment');

        $checkoutUrl = $body['data']['checkout_url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            throw new ChapaException('Chapa did not return a checkout link.');
        }

        return $checkoutUrl;
    }

    // PAY-08: asks Chapa for the real state of one transaction. Returns Chapa's whole answer.
    public function verify(string $txRef): array
    {
        return $this->send('get', '/v1/transaction/verify/'.rawurlencode($txRef), [], 'verify the payment');
    }

    private function send(string $method, string $path, array $payload, string $action): array
    {
        try {
            $response = $this->request()->{$method}(config('chapa.base_url').$path, $payload);
        } catch (ConnectionException) {
            // The exception text can contain the whole request, so it is NOT copied into our message.
            throw new ChapaException("Chapa could not be reached to {$action} (timeout or network error). Try again in a moment.");
        }

        return $this->decode($response, $action);
    }

    private function decode(Response $response, string $action): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        // An error is an HTTP failure, or an answer that is not "success" and carries no transaction data.
        // (For verify, the state of the payment is data.status, which the caller reads.)
        if (! $response->successful() || (($body['status'] ?? null) !== 'success' && ! is_array($body['data'] ?? null))) {
            $message = $body['message'] ?? null;
            $message = is_array($message) ? implode('; ', Arr::flatten($message)) : (string) $message;
            $message = $message !== '' ? ': '.mb_substr($message, 0, 200) : '';

            throw new ChapaException("Chapa could not {$action} (HTTP {$response->status()}){$message}");
        }

        return $body;
    }

    private function request(): PendingRequest
    {
        $key = config('chapa.secret_key');

        if (! is_string($key) || trim($key) === '') {
            throw new ChapaException('Chapa is not configured: set CHAPA_SECRET_KEY in the .env file.');
        }

        $delays = array_values((array) config('chapa.retry_delays_ms', [500, 1500]));

        return Http::withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('chapa.timeout_seconds', 15))
            // Only connection problems and Chapa's own server errors (5xx) are retried: a 4xx answer means OUR
            // request is wrong, and sending it again will not help.
            ->retry(
                count($delays) + 1,
                fn (int $attempt) => $delays[$attempt - 1] ?? 0,
                fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                false, // a failed answer is not thrown here: decode() turns it into a ChapaException
            );
    }
}