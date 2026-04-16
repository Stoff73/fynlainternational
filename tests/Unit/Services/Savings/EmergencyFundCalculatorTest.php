<?php

declare(strict_types=1);

use App\Services\Savings\EmergencyFundCalculator;

describe('EmergencyFundCalculator', function () {
    describe('calculateRunway', function () {
        it('calculates runway correctly with positive values', function () {
            $calculator = new EmergencyFundCalculator;
            $runway = $calculator->calculateRunway(12000, 2000);
            expect($runway)->toBe(6.0);
        });

        it('returns zero when monthly expenditure is zero', function () {
            $calculator = new EmergencyFundCalculator;
            $runway = $calculator->calculateRunway(12000, 0);
            expect($runway)->toBe(0.0);
        });

        it('returns zero when monthly expenditure is negative', function () {
            $calculator = new EmergencyFundCalculator;
            $runway = $calculator->calculateRunway(12000, -100);
            expect($runway)->toBe(0.0);
        });

        it('handles decimal results', function () {
            $calculator = new EmergencyFundCalculator;
            $runway = $calculator->calculateRunway(5500, 2000);
            expect($runway)->toBe(2.75);
        });
    });

    describe('calculateAdequacy', function () {
        it('returns 100% adequacy when runway meets target', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(6.0, 6);
            expect($adequacy['adequacy_score'])->toBe(100.0);
            expect($adequacy['shortfall'])->toBe(0.0);
        });

        it('returns 50% adequacy when runway is half of target', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(3.0, 6);
            expect($adequacy['adequacy_score'])->toBe(50.0);
            expect($adequacy['shortfall'])->toBe(3.0);
        });

        it('caps adequacy score at 100% when runway exceeds target', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(12.0, 6);
            expect($adequacy['adequacy_score'])->toBe(100.0);
            expect($adequacy['shortfall'])->toBe(0.0);
        });

        it('returns correct structure', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(3.0, 6);
            expect($adequacy)->toHaveKeys(['runway', 'target', 'adequacy_score', 'shortfall']);
        });
    });

    describe('calculateMonthlyTopUp', function () {
        it('calculates monthly top-up correctly', function () {
            $calculator = new EmergencyFundCalculator;
            $topUp = $calculator->calculateMonthlyTopUp(12000, 12);
            expect($topUp)->toBe(1000.0);
        });

        it('returns zero when months is zero', function () {
            $calculator = new EmergencyFundCalculator;
            $topUp = $calculator->calculateMonthlyTopUp(12000, 0);
            expect($topUp)->toBe(0.0);
        });

        it('handles negative months gracefully', function () {
            $calculator = new EmergencyFundCalculator;
            $topUp = $calculator->calculateMonthlyTopUp(12000, -5);
            expect($topUp)->toBe(0.0);
        });
    });

    describe('categorizeAdequacy', function () {
        it('returns Excellent for 6+ months runway', function () {
            $calculator = new EmergencyFundCalculator;
            expect($calculator->categorizeAdequacy(6.0))->toBe('Excellent');
            expect($calculator->categorizeAdequacy(12.0))->toBe('Excellent');
        });

        it('returns Good for 3-6 months runway', function () {
            $calculator = new EmergencyFundCalculator;
            expect($calculator->categorizeAdequacy(3.0))->toBe('Good');
            expect($calculator->categorizeAdequacy(5.99))->toBe('Good');
        });

        it('returns Fair for 1-3 months runway', function () {
            $calculator = new EmergencyFundCalculator;
            expect($calculator->categorizeAdequacy(1.0))->toBe('Fair');
            expect($calculator->categorizeAdequacy(2.99))->toBe('Fair');
        });

        it('returns Critical for less than 1 month runway', function () {
            $calculator = new EmergencyFundCalculator;
            expect($calculator->categorizeAdequacy(0.5))->toBe('Critical');
            expect($calculator->categorizeAdequacy(0.0))->toBe('Critical');
        });
    });
});
