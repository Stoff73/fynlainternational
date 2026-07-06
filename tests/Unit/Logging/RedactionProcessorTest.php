<?php

declare(strict_types=1);

use App\Logging\RedactionProcessor;
use Monolog\Level;
use Monolog\LogRecord;

function makeRecord(string $message, array $context = [], array $extra = []): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable('2026-07-06T00:00:00+00:00'),
        channel: 'testing',
        level: Level::Info,
        message: $message,
        context: $context,
        extra: $extra,
    );
}

it('redacts sensitive context keys wholesale', function () {
    $out = (new RedactionProcessor)(makeRecord('login', [
        'email' => 'user@example.com',
        'password' => 'hunter2',
        'api_key' => 'abc123',
        'authorization' => 'Bearer xyz',
    ]));

    expect($out->context['password'])->toBe('[REDACTED]')
        ->and($out->context['api_key'])->toBe('[REDACTED]')
        ->and($out->context['authorization'])->toBe('[REDACTED]')
        ->and($out->context['email'])->toBe('user@example.com'); // non-sensitive key untouched
});

it('masks secret-shaped tokens in the message', function () {
    $out = (new RedactionProcessor)(makeRecord(
        'Revolut sk_live_ABCDEFGH12345678 and xai-abcdef1234567890 sent'
    ));

    expect($out->message)->not->toContain('sk_live_ABCDEFGH12345678')
        ->and($out->message)->not->toContain('xai-abcdef1234567890')
        ->and($out->message)->toContain('[REDACTED]');
});

it('masks a Sanctum plain-text token in nested context', function () {
    $out = (new RedactionProcessor)(makeRecord('token issued', [
        'meta' => ['plainTextToken' => '5|abcdefghijklmnopqrstuvwxyz0123456789', 'ok' => 'keep'],
    ]));

    expect($out->context['meta']['plainTextToken'])->toBe('[REDACTED]')
        ->and($out->context['meta']['ok'])->toBe('keep');
});

it('masks a Bearer header appearing in a string value', function () {
    $out = (new RedactionProcessor)(makeRecord('req', [
        'header' => 'Authorization: Bearer abcdefghijklmnop1234567890',
    ]));

    expect($out->context['header'])->toContain('[REDACTED]')
        ->and($out->context['header'])->not->toContain('abcdefghijklmnop1234567890');
});

it('leaves ordinary log lines unchanged', function () {
    $out = (new RedactionProcessor)(makeRecord('user 42 updated pension', ['user_id' => 42]));

    expect($out->message)->toBe('user 42 updated pension')
        ->and($out->context['user_id'])->toBe(42);
});
