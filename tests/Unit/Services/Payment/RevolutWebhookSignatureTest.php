<?php

declare(strict_types=1);

use App\Services\Payment\RevolutService;

describe('G-4-b slice 2 — M-5: RevolutService::verifyWebhookSignature', function () {

    beforeEach(function () {
        config()->set('services.revolut.webhook_secret', 'whsec_test_secret_for_unit_tests');
        $this->service = new RevolutService;
    });

    function signaturePayload(string $payload, string $secret, int $timestampMs): array
    {
        $payloadToSign = "v1.{$timestampMs}.{$payload}";
        $sig = 'v1='.hash_hmac('sha256', $payloadToSign, $secret);

        return [
            'signature' => $sig,
            'timestamp' => (string) $timestampMs,
        ];
    }

    it('accepts a valid signature within the timestamp tolerance', function () {
        $payload = '{"event":"ORDER_COMPLETED","order_id":"abc"}';
        $now = (int) (microtime(true) * 1000);
        $secret = config('services.revolut.webhook_secret');

        $sig = signaturePayload($payload, $secret, $now);

        expect($this->service->verifyWebhookSignature($payload, $sig['signature'], $sig['timestamp']))
            ->toBeTrue();
    });

    it('rejects when timestamp is more than 5 minutes in the past', function () {
        $payload = '{"event":"ORDER_COMPLETED"}';
        $oldTimestamp = (int) (microtime(true) * 1000) - (6 * 60 * 1000);
        $secret = config('services.revolut.webhook_secret');

        $sig = signaturePayload($payload, $secret, $oldTimestamp);

        expect($this->service->verifyWebhookSignature($payload, $sig['signature'], $sig['timestamp']))
            ->toBeFalse();
    });

    it('rejects when timestamp is more than 5 minutes in the future (clock-skew DoS)', function () {
        $payload = '{"event":"ORDER_COMPLETED"}';
        $futureTimestamp = (int) (microtime(true) * 1000) + (6 * 60 * 1000);
        $secret = config('services.revolut.webhook_secret');

        $sig = signaturePayload($payload, $secret, $futureTimestamp);

        expect($this->service->verifyWebhookSignature($payload, $sig['signature'], $sig['timestamp']))
            ->toBeFalse();
    });

    it('rejects when secret is not configured', function () {
        config()->set('services.revolut.webhook_secret', '');
        $service = new RevolutService;

        $now = (int) (microtime(true) * 1000);
        $payload = '{"event":"ORDER_COMPLETED"}';

        expect($service->verifyWebhookSignature($payload, 'v1=anything', (string) $now))
            ->toBeFalse();
    });

    it('rejects when signature does not match expected HMAC', function () {
        $payload = '{"event":"ORDER_COMPLETED"}';
        $now = (int) (microtime(true) * 1000);

        $tamperedSig = 'v1='.hash_hmac('sha256', "v1.{$now}.{$payload}", 'wrong-secret');

        expect($this->service->verifyWebhookSignature($payload, $tamperedSig, (string) $now))
            ->toBeFalse();
    });

    it('accepts when one of multiple signatures matches (key rotation)', function () {
        $payload = '{"event":"ORDER_COMPLETED"}';
        $now = (int) (microtime(true) * 1000);
        $secret = config('services.revolut.webhook_secret');

        $validSig = 'v1='.hash_hmac('sha256', "v1.{$now}.{$payload}", $secret);
        $junkSig = 'v1='.hash_hmac('sha256', "v1.{$now}.{$payload}", 'old-rotated-secret');
        $header = "{$junkSig}, {$validSig}";

        expect($this->service->verifyWebhookSignature($payload, $header, (string) $now))
            ->toBeTrue();
    });

    it('rejects when only non-v1 signatures are present', function () {
        $payload = '{"event":"ORDER_COMPLETED"}';
        $now = (int) (microtime(true) * 1000);
        $secret = config('services.revolut.webhook_secret');

        $v2Sig = 'v2='.hash_hmac('sha256', "v1.{$now}.{$payload}", $secret);

        expect($this->service->verifyWebhookSignature($payload, $v2Sig, (string) $now))
            ->toBeFalse();
    });

    it('rejects when payload bytes differ from what was signed', function () {
        $signedPayload = '{"event":"ORDER_COMPLETED","amount":1000}';
        $tamperedPayload = '{"event":"ORDER_COMPLETED","amount":10000}';
        $now = (int) (microtime(true) * 1000);
        $secret = config('services.revolut.webhook_secret');

        $sig = signaturePayload($signedPayload, $secret, $now);

        expect($this->service->verifyWebhookSignature($tamperedPayload, $sig['signature'], $sig['timestamp']))
            ->toBeFalse();
    });
});
