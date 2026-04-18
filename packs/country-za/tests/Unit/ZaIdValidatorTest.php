<?php

declare(strict_types=1);

use Fynla\Core\Contracts\IdentityValidator;
use Fynla\Packs\Za\Identity\ZaIdValidator;

beforeEach(function () {
    $this->v = new ZaIdValidator();
});

it('implements the IdentityValidator contract', function () {
    expect($this->v)->toBeInstanceOf(IdentityValidator::class);
});

it('validates a well-formed SA ID with correct Luhn digit', function () {
    // A valid test ID (born 1 January 1990, male, SA citizen).
    // Build programmatically so the Luhn passes.
    $base = '9001015800088';  // YYMMDD SSSS C A Z
    // The last digit is the Luhn check — compute it.
    $twelve = substr($base, 0, 12);
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $d = (int) $twelve[11 - $i];
        if ($i % 2 === 0) {
            $d *= 2;
            if ($d > 9) {
                $d -= 9;
            }
        }
        $sum += $d;
    }
    $check = (10 - ($sum % 10)) % 10;
    $valid = $twelve . $check;

    expect($this->v->validate($valid))->toBeTrue();
});

it('rejects non-numeric', function () {
    expect($this->v->validate('abc'))->toBeFalse();
});

it('rejects wrong length', function () {
    expect($this->v->validate('123456789'))->toBeFalse();
    expect($this->v->validate('12345678901234'))->toBeFalse();
});

it('rejects invalid month/day', function () {
    expect($this->v->validate('9013015800088'))->toBeFalse();  // month 13
    expect($this->v->validate('9001325800088'))->toBeFalse();  // day 32
});

it('extracts DOB + gender + citizenship from valid ID', function () {
    // Build a valid 1990-01-01 female SA citizen.
    $twelve = '900101050008';  // YYMMDD 0500 0 8 — female, citizen
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $d = (int) $twelve[11 - $i];
        if ($i % 2 === 0) {
            $d *= 2;
            if ($d > 9) {
                $d -= 9;
            }
        }
        $sum += $d;
    }
    $check = (10 - ($sum % 10)) % 10;
    $id = $twelve . $check;

    $meta = $this->v->extractMetadata($id);

    expect($meta['date_of_birth'])->toBe('1990-01-01');
    expect($meta['gender'])->toBe('female');
    expect($meta['citizenship'])->toBe('citizen');
});
