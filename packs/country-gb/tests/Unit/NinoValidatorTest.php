<?php

declare(strict_types=1);

use Fynla\Core\Contracts\IdentityValidator;
use Fynla\Packs\Gb\Validation\NinoValidator;

beforeEach(function () {
    $this->v = new NinoValidator();
});

it('implements the IdentityValidator contract', function () {
    expect($this->v)->toBeInstanceOf(IdentityValidator::class);
});

it('resolves from the container with the GB identity binding', function () {
    expect(app('pack.gb.identity'))->toBeInstanceOf(NinoValidator::class);
});

it('accepts a well-formed NINO with and without spaces', function () {
    expect($this->v->validate('AB123456C'))->toBeTrue();
    expect($this->v->validate('AB 12 34 56 C'))->toBeTrue();
    expect($this->v->validate('ab123456c'))->toBeTrue();  // lowercase normalised
});

it('rejects NINOs with the wrong overall format', function () {
    expect($this->v->validate('123456789'))->toBeFalse();      // all digits
    expect($this->v->validate('ABC12345D'))->toBeFalse();      // 3-letter prefix
    expect($this->v->validate('AB12345C'))->toBeFalse();       // 5 digits
    expect($this->v->validate('AB1234567C'))->toBeFalse();     // 7 digits
    expect($this->v->validate(''))->toBeFalse();
});

it('rejects forbidden first letters (D, F, I, Q, U, V)', function () {
    expect($this->v->validate('DA123456C'))->toBeFalse();
    expect($this->v->validate('FA123456C'))->toBeFalse();
    expect($this->v->validate('IA123456C'))->toBeFalse();
    expect($this->v->validate('QA123456C'))->toBeFalse();
    expect($this->v->validate('UA123456C'))->toBeFalse();
    expect($this->v->validate('VA123456C'))->toBeFalse();
});

it('rejects forbidden second letters (D, F, I, O, Q, U, V)', function () {
    expect($this->v->validate('AD123456C'))->toBeFalse();
    expect($this->v->validate('AO123456C'))->toBeFalse();
    expect($this->v->validate('AQ123456C'))->toBeFalse();
});

it('rejects forbidden prefix pairs (BG, GB, KN, NK, NT, TN, ZZ)', function () {
    expect($this->v->validate('BG123456C'))->toBeFalse();
    expect($this->v->validate('GB123456C'))->toBeFalse();
    expect($this->v->validate('KN123456C'))->toBeFalse();
    expect($this->v->validate('NK123456C'))->toBeFalse();
    expect($this->v->validate('NT123456C'))->toBeFalse();
    expect($this->v->validate('TN123456C'))->toBeFalse();
    expect($this->v->validate('ZZ123456C'))->toBeFalse();
});

it('rejects suffixes outside A/B/C/D', function () {
    expect($this->v->validate('AB123456E'))->toBeFalse();
    expect($this->v->validate('AB123456Z'))->toBeFalse();
});

it('returns an empty metadata array (NINOs encode no demographic data)', function () {
    expect($this->v->extractMetadata('AB123456C'))->toBe([]);
});
