<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Fynla\Packs\Gb\Models\JointAccountLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JointAccountLogController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Get joint account logs for the authenticated user.
     * Returns logs where user is either the editor or the joint owner.
     *
     * GET /api/joint-account-logs
     * Query params:
     *   - type: filter by loggable_type (property, mortgage, investment, savings)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type');

        $query = JointAccountLog::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })
            ->with(['user:id,name,email', 'jointOwner:id,name,email', 'loggable'])
            ->orderBy('created_at', 'desc');

        // Filter by type if specified. Values are the relocated UK pack
        // FQCNs (R-4); legacy rows are backfilled by the data migration.
        if ($type) {
            $typeMap = [
                'property' => \Fynla\Packs\Gb\Models\Property::class,
                'mortgage' => \Fynla\Packs\Gb\Models\Mortgage::class,
                'investment' => \Fynla\Packs\Gb\Models\Investment\InvestmentAccount::class,
                'savings' => \Fynla\Packs\Gb\Models\SavingsAccount::class,
            ];

            if (isset($typeMap[$type])) {
                $query->where('loggable_type', $typeMap[$type]);
            }
        }

        $logs = $query->get();

        // Format logs for frontend
        $formattedLogs = $logs->map(function ($log) use ($user) {
            // Safe null checks for user and jointOwner relationships
            $userName = $log->user?->name ?? 'Unknown User';
            $jointOwnerName = $log->jointOwner?->name ?? 'Unknown User';

            $editedBy = $log->user_id === $user->id ? 'You' : $userName;
            $affectedUser = $log->joint_owner_id === $user->id ? 'your' : ($jointOwnerName."'s");

            // Determine asset type for display. R-4: the FQCN in
            // loggable_type now points at the GB pack namespace. Legacy
            // App\Models\X rows are backfilled by the data migration.
            $assetType = match ($log->loggable_type) {
                \Fynla\Packs\Gb\Models\Property::class => 'property',
                \Fynla\Packs\Gb\Models\Mortgage::class => 'mortgage',
                \Fynla\Packs\Gb\Models\Investment\InvestmentAccount::class => 'investment',
                \Fynla\Packs\Gb\Models\SavingsAccount::class => 'savings',
                default => 'account',
            };

            $assetName = match ($log->loggable_type) {
                \Fynla\Packs\Gb\Models\Property::class => $log->loggable?->address_line_1 ?? 'Unknown Property',
                \Fynla\Packs\Gb\Models\Mortgage::class => $log->loggable?->lender_name ?? 'Unknown Mortgage',
                \Fynla\Packs\Gb\Models\Investment\InvestmentAccount::class => $log->loggable?->account_name ?? 'Unknown Investment',
                \Fynla\Packs\Gb\Models\SavingsAccount::class => $log->loggable?->account_name ?? 'Unknown Savings',
                default => 'Unknown Asset',
            };

            return [
                'id' => $log->id,
                'edited_by' => $editedBy,
                'edited_by_name' => $userName,
                'affected_user' => $affectedUser,
                'affected_user_name' => $jointOwnerName,
                'asset_type' => $assetType,
                'asset_name' => $assetName,
                'action' => $log->action,
                'changes' => $log->changes,
                'created_at' => $log->created_at->toIso8601String(),
                'formatted_date' => $log->created_at->format('d/m/Y'),
                'formatted_time' => $log->created_at->format('H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $formattedLogs,
                'count' => $formattedLogs->count(),
            ],
        ]);
    }
}
