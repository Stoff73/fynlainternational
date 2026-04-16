<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

use Fynla\Core\Registry\PackManifest;

/**
 * Master interface that every country pack must implement.
 *
 * A country pack encapsulates all jurisdiction-specific financial logic,
 * tax rules, product definitions, and localisation for a single country.
 */
interface CountryPack
{
    /**
     * ISO 3166-1 alpha-2 country code (e.g. "GB", "ZA", "US").
     */
    public function code(): string;

    /**
     * The pack's manifest describing its metadata, routes, and navigation.
     */
    public function manifest(): PackManifest;

    /**
     * Tax calculation engine for the jurisdiction.
     */
    public function taxEngine(): TaxEngine;

    /**
     * Retirement planning engine for the jurisdiction.
     */
    public function retirementEngine(): RetirementEngine;

    /**
     * Investment product and wrapper engine for the jurisdiction.
     */
    public function investmentEngine(): InvestmentEngine;

    /**
     * Insurance and protection product engine for the jurisdiction.
     */
    public function protectionEngine(): ProtectionEngine;

    /**
     * Estate and inheritance planning engine for the jurisdiction.
     */
    public function estateEngine(): EstateEngine;

    /**
     * Foreign exchange control rules for the jurisdiction.
     */
    public function exchangeControl(): ExchangeControl;

    /**
     * National identity number validator for the jurisdiction.
     */
    public function identityValidator(): IdentityValidator;

    /**
     * Bank account and routing code validator for the jurisdiction.
     */
    public function banking(): BankingValidator;

    /**
     * Localisation provider (currency, date format, terminology).
     */
    public function localisation(): Localisation;
}
