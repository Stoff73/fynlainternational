<?php

declare(strict_types=1);

describe('Pack binding singleton identity (G-(-1) FR-M2)', function () {
    it('resolves pack.gb.asset_repo to the same instance every time', function () {
        expect(app()->make('pack.gb.asset_repo'))->toBe(app()->make('pack.gb.asset_repo'));
    });

    it('resolves pack.gb.estate_repo to the same instance every time', function () {
        expect(app()->make('pack.gb.estate_repo'))->toBe(app()->make('pack.gb.estate_repo'));
    });

    it('resolves pack.gb.asset_resolver to the same instance every time', function () {
        expect(app()->make('pack.gb.asset_resolver'))->toBe(app()->make('pack.gb.asset_resolver'));
    });

    it('resolves pack.gb.user_relations to the same instance every time', function () {
        expect(app()->make('pack.gb.user_relations'))->toBe(app()->make('pack.gb.user_relations'));
    });
});
