<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Cross-module tax optimisation contract for a jurisdiction.
 *
 * Unlike TaxEngine (which exposes pure-function rate / band lookups), this
 * contract orchestrates per-user analysis: which allowances are unused,
 * which strategies would surface the largest tax saving, and which what-if
 * scenarios are worth modelling. Implementations therefore take a user id
 * and return jurisdiction-shaped advice payloads, not isolated calculations.
 *
 * Return shapes follow the BaseAgent envelope used by Fynla agents:
 * ['success' => bool, 'message' => string, 'data' => array].
 *
 * Monetary values inside the data array are expressed in major currency
 * units (£ / R) for compatibility with the existing UK agent's wire format.
 * Future packs may emit minor units; consumers should consult the
 * jurisdiction-specific shape contract for the unit convention.
 */
interface TaxOptimisationEngine
{
    /**
     * Analyse a user's cross-module tax position for the active tax year.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function analyze(int $userId): array;

    /**
     * Convert an analysis payload into prioritised, action-bearing
     * recommendations. The input is the data array returned by analyze()
     * (or the full envelope — implementations unwrap as needed).
     *
     * @param  array<string, mixed>  $analysisData
     * @return array{recommendation_count: int, recommendations: array<int, array<string, mixed>>}
     */
    public function generateRecommendations(array $analysisData): array;

    /**
     * Build what-if scenarios for a user using caller-supplied parameters.
     * Parameter shape is jurisdiction-specific — implementations declare
     * which keys they read and tolerate additional keys.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function buildScenarios(int $userId, array $parameters): array;
}
