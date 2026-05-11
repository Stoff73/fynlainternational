<?php

declare(strict_types=1);

namespace Fynla\Core\Models;

use App\Models\User;
use Fynla\Core\Contracts\GoalCalculationEngine;
use Fynla\Core\Contracts\PackAssetResolver;
use Fynla\Core\Traits\Auditable;
use Fynla\Core\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Goal extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    /**
     * @var array<int, string>
     *
     * Note on linking mechanisms:
     *
     * - linked_account_ids (JSON array): Investment-goal-specific field. Stores an array of
     *   InvestmentAccount IDs that fund this goal. Used by the Investment GoalForm.vue and
     *   InvestmentGoal model. NOT used for savings-goal linking — do NOT repurpose for savings.
     *
     * - linked_savings_account_id (FK): Legacy single savings account link. Being superseded
     *   by the goal_savings_account pivot table (many-to-many). Retained for backwards
     *   compatibility.
     *
     * - linked_investment_account_id (FK): Single investment account link used by the
     *   TracksGoalContributions trait for auto-recording contributions when account value changes.
     */
    protected $fillable = [
        'user_id',
        'goal_name',
        'goal_type',
        'custom_goal_type_name',
        'description',
        'target_amount',
        'current_amount',
        'target_date',
        'start_date',
        'assigned_module',
        'module_override',
        'priority',
        'is_essential',
        'status',
        'monthly_contribution',
        'contribution_frequency',
        'contribution_streak',
        'longest_streak',
        'last_contribution_date',
        'linked_account_ids',
        'linked_savings_account_id',
        'linked_investment_account_id',
        'risk_preference',
        'use_global_risk_profile',
        'ownership_type',
        'joint_owner_id',
        'ownership_percentage',
        'show_in_projection',
        'show_in_household_view',
        'property_location',
        'property_type',
        'is_first_time_buyer',
        'estimated_property_price',
        'deposit_percentage',
        'stamp_duty_estimate',
        'additional_costs_estimate',
        'milestones',
        'projection_data',
        'completed_at',
        'completion_notes',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'target_date' => 'date',
        'start_date' => 'date',
        'module_override' => 'boolean',
        'is_essential' => 'boolean',
        'monthly_contribution' => 'decimal:2',
        'contribution_streak' => 'integer',
        'longest_streak' => 'integer',
        'last_contribution_date' => 'date',
        'linked_account_ids' => 'array',
        'risk_preference' => 'integer',
        'use_global_risk_profile' => 'boolean',
        'ownership_percentage' => 'decimal:2',
        'show_in_projection' => 'boolean',
        'show_in_household_view' => 'boolean',
        'is_first_time_buyer' => 'boolean',
        'estimated_property_price' => 'decimal:2',
        'deposit_percentage' => 'decimal:2',
        'stamp_duty_estimate' => 'decimal:2',
        'additional_costs_estimate' => 'decimal:2',
        'milestones' => 'array',
        'projection_data' => 'array',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'progress_percentage',
        'days_remaining',
        'months_remaining',
        'is_on_track',
        'display_goal_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Resolve the linked savings account via PackAssetResolver.
     *
     * Pre-R-14b-v this was a `belongsTo(SavingsAccount::class)` relation.
     * After Goal moved to core, the pack-namespaced literal was replaced
     * with a contract call so core no longer references the SA model.
     *
     * Accessed as `$goal->linkedSavingsAccount` via Eloquent's accessor
     * magic on the matching `getLinkedSavingsAccountAttribute()`.
     */
    public function getLinkedSavingsAccountAttribute(): ?Model
    {
        if ($this->linked_savings_account_id === null) {
            return null;
        }

        return app(PackAssetResolver::class)
            ->resolveAccount('gb.savings_account', (int) $this->linked_savings_account_id);
    }

    public function getLinkedInvestmentAccountAttribute(): ?Model
    {
        if ($this->linked_investment_account_id === null) {
            return null;
        }

        return app(PackAssetResolver::class)
            ->resolveAccount('gb.investment_account', (int) $this->linked_investment_account_id);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'goal_dependencies', 'goal_id', 'depends_on_goal_id')
            ->withPivot('dependency_type', 'notes')
            ->withTimestamps();
    }

    /**
     * Savings accounts linked to this goal via the goal_savings_account
     * pivot table. Pre-R-14b-v this was a `belongsToMany(SavingsAccount)`
     * relation; the pack model literal was replaced with a per-row
     * PackAssetResolver call so core no longer references the pack model.
     *
     * Returns a Collection of resolved Model instances (skipping rows
     * the resolver returns null for — e.g. a soft-deleted account). The
     * pivot table's own attributes (allocated_amount, is_primary,
     * priority_rank) are NOT attached to the model — callers that need
     * them should query the pivot table directly. None of the current
     * call sites in app/ or packs/ read pivot columns.
     *
     * Accessed as `$goal->savingsAccounts` via accessor magic; the
     * snake-case form `$goal->savings_accounts` also works.
     */
    public function getSavingsAccountsAttribute(): Collection
    {
        $resolver = app(PackAssetResolver::class);

        return DB::table('goal_savings_account')
            ->where('goal_id', $this->id)
            ->pluck('savings_account_id')
            ->map(fn ($id) => $resolver->resolveAccount('gb.savings_account', (int) $id))
            ->filter()
            ->values();
    }

    public function dependedOnBy(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'goal_dependencies', 'depends_on_goal_id', 'goal_id')
            ->withPivot('dependency_type', 'notes')
            ->withTimestamps();
    }

    public function isBlocked(): bool
    {
        return $this->dependsOn()
            ->wherePivot('dependency_type', 'blocks')
            ->where('status', '!=', 'completed')
            ->exists();
    }

    public function getProgressPercentageAttribute(): float
    {
        return app(GoalCalculationEngine::class)->calculateProgressPercentage($this);
    }

    public function getDaysRemainingAttribute(): int
    {
        return app(GoalCalculationEngine::class)->calculateDaysRemaining($this);
    }

    public function getMonthsRemainingAttribute(): int
    {
        return app(GoalCalculationEngine::class)->calculateMonthsRemaining($this);
    }

    public function getIsOnTrackAttribute(): bool
    {
        return app(GoalCalculationEngine::class)->calculateIsOnTrack($this);
    }

    public function getDisplayGoalTypeAttribute(): string
    {
        if ($this->goal_type === 'custom' && $this->custom_goal_type_name) {
            return $this->custom_goal_type_name;
        }

        return match ($this->goal_type) {
            'emergency_fund' => 'Emergency Fund',
            'property_purchase' => 'Property Purchase',
            'home_deposit' => 'Home Deposit',
            'education' => 'Education',
            'retirement' => 'Retirement',
            'wealth_accumulation' => 'Wealth Building',
            'wedding' => 'Wedding',
            'holiday' => 'Holiday',
            'car_purchase' => 'Car Purchase',
            'debt_repayment' => 'Debt Repayment',
            'custom' => 'Custom Goal',
            default => ucfirst(str_replace('_', ' ', $this->goal_type ?? '')),
        };
    }

    public function getAmountRemainingAttribute(): float
    {
        return app(GoalCalculationEngine::class)->calculateAmountRemaining($this);
    }

    public function getRequiredMonthlyContributionAttribute(): float
    {
        return app(GoalCalculationEngine::class)->calculateRequiredMonthlyContribution($this);
    }

    public function isPropertyGoal(): bool
    {
        return in_array($this->goal_type, ['property_purchase', 'home_deposit']);
    }

    public function isInvestmentGoal(): bool
    {
        return $this->assigned_module === 'investment';
    }

    public function isJoint(): bool
    {
        return $this->ownership_type === 'joint' && $this->joint_owner_id !== null;
    }

    public function getCurrentMilestoneAttribute(): ?int
    {
        return app(GoalCalculationEngine::class)->calculateCurrentMilestone($this);
    }

    public function getNextMilestoneAttribute(): ?int
    {
        return app(GoalCalculationEngine::class)->calculateNextMilestone($this);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('assigned_module', $module);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }
}
