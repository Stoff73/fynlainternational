<?php

declare(strict_types=1);

use Fynla\Core\Jurisdiction\CountryResolver;

beforeEach(function () {
    $this->resolver = new CountryResolver;
});

describe('CountryResolver', function () {

    describe('ISO code resolution', function () {
        it('resolves uppercase ISO codes', function () {
            expect($this->resolver->resolve('GB'))->toBe('gb');
            expect($this->resolver->resolve('ZA'))->toBe('za');
        });

        it('resolves lowercase ISO codes', function () {
            expect($this->resolver->resolve('gb'))->toBe('gb');
            expect($this->resolver->resolve('za'))->toBe('za');
        });

        it('rejects ISO codes outside the supported set', function () {
            expect($this->resolver->resolve('US'))->toBeNull();
            expect($this->resolver->resolve('FR'))->toBeNull();
            expect($this->resolver->resolve('XX'))->toBeNull();
        });
    });

    describe('alias resolution', function () {
        it('maps common UK aliases', function () {
            expect($this->resolver->resolve('UK'))->toBe('gb');
            expect($this->resolver->resolve('uk'))->toBe('gb');
            expect($this->resolver->resolve('Britain'))->toBe('gb');
            expect($this->resolver->resolve('Great Britain'))->toBe('gb');
            expect($this->resolver->resolve('England'))->toBe('gb');
            expect($this->resolver->resolve('Scotland'))->toBe('gb');
            expect($this->resolver->resolve('Wales'))->toBe('gb');
            expect($this->resolver->resolve('Northern Ireland'))->toBe('gb');
        });

        it('maps common SA aliases', function () {
            expect($this->resolver->resolve('SA'))->toBe('za');
            expect($this->resolver->resolve('R.S.A.'))->toBe('za');
        });
    });

    describe('official country name resolution', function () {
        it('matches "United Kingdom"', function () {
            expect($this->resolver->resolve('United Kingdom'))->toBe('gb');
            expect($this->resolver->resolve('united kingdom'))->toBe('gb');
        });

        it('matches "South Africa"', function () {
            expect($this->resolver->resolve('South Africa'))->toBe('za');
            expect($this->resolver->resolve('Republic of South Africa'))->toBe('za');
        });
    });

    describe('city resolution', function () {
        it('resolves major UK cities', function () {
            expect($this->resolver->resolve('London'))->toBe('gb');
            expect($this->resolver->resolve('Edinburgh'))->toBe('gb');
            expect($this->resolver->resolve('Manchester'))->toBe('gb');
            expect($this->resolver->resolve('Cardiff'))->toBe('gb');
        });

        it('resolves major SA cities', function () {
            expect($this->resolver->resolve('Cape Town'))->toBe('za');
            expect($this->resolver->resolve('Johannesburg'))->toBe('za');
            expect($this->resolver->resolve('Pretoria'))->toBe('za');
            expect($this->resolver->resolve('Durban'))->toBe('za');
        });

        it('handles extra whitespace and case variation', function () {
            expect($this->resolver->resolve('  cape  town  '))->toBe('za');
            expect($this->resolver->resolve('LONDON'))->toBe('gb');
        });
    });

    describe('unresolved input', function () {
        it('returns null for unknown input', function () {
            expect($this->resolver->resolve('Atlantis'))->toBeNull();
            expect($this->resolver->resolve('New York'))->toBeNull();
            expect($this->resolver->resolve('Paris'))->toBeNull();
        });

        it('returns null for empty or null input', function () {
            expect($this->resolver->resolve(''))->toBeNull();
            expect($this->resolver->resolve('   '))->toBeNull();
            expect($this->resolver->resolve(null))->toBeNull();
        });
    });

    describe('isSupported', function () {
        it('accepts supported codes regardless of case', function () {
            expect($this->resolver->isSupported('GB'))->toBeTrue();
            expect($this->resolver->isSupported('gb'))->toBeTrue();
            expect($this->resolver->isSupported('ZA'))->toBeTrue();
        });

        it('rejects unsupported codes', function () {
            expect($this->resolver->isSupported('US'))->toBeFalse();
            expect($this->resolver->isSupported('united kingdom'))->toBeFalse();
        });
    });

    describe('supportedCodes', function () {
        it('returns the canonical list', function () {
            expect($this->resolver->supportedCodes())->toBe(['gb', 'za']);
        });
    });
});
