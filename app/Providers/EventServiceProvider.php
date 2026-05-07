<?php

declare(strict_types=1);

namespace App\Providers;

use Fynla\Packs\Gb\Models\BusinessInterest;
use Fynla\Packs\Gb\Models\Chattel;
use Fynla\Packs\Gb\Models\CriticalIllnessPolicy;
use Fynla\Packs\Gb\Models\DCPension;
use Fynla\Packs\Gb\Models\DisabilityPolicy;
use Fynla\Packs\Gb\Models\Estate\Asset as EstateAsset;
use Fynla\Packs\Gb\Models\Estate\Liability as EstateLiability;
use Fynla\Core\Models\FamilyMember;
use Fynla\Packs\Gb\Models\IncomeProtectionPolicy;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use Fynla\Packs\Gb\Models\LifeInsurancePolicy;
use Fynla\Packs\Gb\Models\Mortgage;
use Fynla\Packs\Gb\Models\Property;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Fynla\Packs\Gb\Models\SicknessIllnessPolicy;
use App\Models\User;
use Fynla\Packs\Gb\Observers\DCPensionRiskObserver;
use App\Observers\FamilyMemberRiskObserver;
use Fynla\Packs\Gb\Observers\InvestmentAccountGoalObserver;
use Fynla\Packs\Gb\Observers\InvestmentAccountRiskObserver;
use App\Observers\LifeEventMonteCarloObserver;
use App\Observers\LifeEventRiskObserver;
use App\Observers\NetWorthCacheObserver;
use Fynla\Packs\Gb\Observers\PropertyRiskObserver;
use App\Observers\RecommendationCacheObserver;
use Fynla\Packs\Gb\Observers\SavingsAccountGoalObserver;
use Fynla\Packs\Gb\Observers\SavingsAccountRiskObserver;
use App\Observers\UserRiskObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * The model observers for your application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $observers = [
        User::class => [UserRiskObserver::class],
        FamilyMember::class => [FamilyMemberRiskObserver::class, RecommendationCacheObserver::class],
        SavingsAccount::class => [SavingsAccountRiskObserver::class, SavingsAccountGoalObserver::class, NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        InvestmentAccount::class => [InvestmentAccountRiskObserver::class, InvestmentAccountGoalObserver::class, NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        DCPension::class => [DCPensionRiskObserver::class, NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        Property::class => [PropertyRiskObserver::class, NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        Mortgage::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        BusinessInterest::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        Chattel::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        EstateAsset::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        EstateLiability::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        LifeEvent::class => [LifeEventMonteCarloObserver::class, LifeEventRiskObserver::class, RecommendationCacheObserver::class],
        LifeInsurancePolicy::class => [RecommendationCacheObserver::class],
        CriticalIllnessPolicy::class => [RecommendationCacheObserver::class],
        IncomeProtectionPolicy::class => [RecommendationCacheObserver::class],
        DisabilityPolicy::class => [RecommendationCacheObserver::class],
        SicknessIllnessPolicy::class => [RecommendationCacheObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
