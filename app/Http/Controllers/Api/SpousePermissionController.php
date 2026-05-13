<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Fynla\Core\Models\Permission;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Cache\CacheInvalidationService;
use Fynla\Core\Models\FamilyMember;
use Fynla\Core\Models\SpousePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpousePermissionController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Get the current permission status with spouse
     *
     * GET /api/spouse-permission/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has spouse_id set (linked spouse account)
        $hasLinkedSpouse = (bool) $user->spouse_id;

        // Also check if user has a spouse in family_members table (may not have linked account)
        $spouseFamilyMember = \Fynla\Core\Models\FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'spouse')
            ->first();

        if (! $hasLinkedSpouse && ! $spouseFamilyMember) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => false,
                    'spouse' => null,
                    'permission' => null,
                ],
            ]);
        }

        // If spouse_id is set, check for permissions
        if ($user->spouse_id) {
            // Check if permission exists (either direction)
            $permission = SpousePermission::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('spouse_id', $user->spouse_id);
            })->orWhere(function ($query) use ($user) {
                $query->where('user_id', $user->spouse_id)
                    ->where('spouse_id', $user->id);
            })->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => true,
                    'spouse' => $user->spouse,
                    'permission' => $permission,
                    'can_view_spouse_data' => $permission && $permission->status === 'accepted',
                ],
            ]);
        }

        // If only family member spouse exists (no linked account)
        return response()->json([
            'success' => true,
            'data' => [
                'has_spouse' => true,
                'spouse' => [
                    'id' => null,
                    'name' => $spouseFamilyMember->name,
                    'email' => null,
                ],
                'permission' => null,
                'can_view_spouse_data' => false,
                'requires_account_link' => true,
                'message' => 'Your spouse needs an account to enable data sharing. Add their email in the Family Members section.',
            ],
        ]);
    }

    /**
     * Request permission to view spouse's data
     *
     * POST /api/spouse-permission/request
     */
    public function request(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->spouse_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a linked spouse',
            ], 422);
        }

        // Check if permission already exists
        $existingPermission = SpousePermission::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('spouse_id', $user->spouse_id);
        })->orWhere(function ($query) use ($user) {
            $query->where('user_id', $user->spouse_id)
                ->where('spouse_id', $user->id);
        })->first();

        if ($existingPermission) {
            return response()->json([
                'success' => false,
                'message' => 'A permission request already exists',
                'data' => ['permission' => $existingPermission],
            ], 422);
        }

        // Create new permission request
        $permission = SpousePermission::create([
            'user_id' => $user->id,
            'spouse_id' => $user->spouse_id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        // Send notification/email to spouse
        $spouse = \Fynla\Core\Models\User::find($user->spouse_id);
        if ($spouse) {
            $spouse->notify(new \App\Notifications\SpousePermissionRequest($user->name));
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission request sent to your spouse',
            'data' => ['permission' => $permission],
        ], 201);
    }

    /**
     * Accept a permission request.
     *
     * POST /api/spouse-permission/accept
     *
     * G-4-b slice 3 H-3: this is now the ONLY path that establishes a spouse
     * link between two existing accounts. If the current user has no linked
     * spouse yet but a pending invitation targets them, accepting finalises
     * the linkage atomically (sets spouse_id + marital_status on both users,
     * creates the reciprocal SpousePermission + FamilyMember record). If the
     * users are already linked (e.g. the invitee created an account via the
     * new-spouse branch in FamilyMembersController), accepting just flips
     * the permission row to `accepted` — original behaviour.
     */
    public function accept(Request $request): JsonResponse
    {
        $user = $request->user();

        // Find ANY pending invitation that targets the current user.
        // (We no longer require $user->spouse_id to be set, because the
        // invitee path is now consent-first — link is established at accept.)
        $permission = SpousePermission::where('spouse_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No pending permission request found',
            ], 404);
        }

        // If the user already has a different spouse linked, refuse — only
        // one spouse linkage at a time.
        if ($user->spouse_id && $user->spouse_id !== $permission->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are already linked to a different spouse',
            ], 422);
        }

        $inviter = \Fynla\Core\Models\User::find($permission->user_id);
        if (! $inviter) {
            return response()->json([
                'success' => false,
                'message' => 'Inviter account no longer exists',
            ], 404);
        }

        if ($inviter->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid permission request',
            ], 422);
        }

        DB::transaction(function () use ($user, $inviter, $permission) {
            // Always flip the original permission row to accepted.
            $permission->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            // If users are not yet linked, this accept also performs the
            // link finalisation. The inviter never gets to mutate any of
            // the invitee's own fields — the invitee inherits nothing from
            // the inviter beyond the marital-status change implied by
            // explicit consent.
            if (! $user->spouse_id) {
                // Lock the inviter row to avoid racing a parallel accept
                // from a different invitee invited by the same user.
                $inviter = \Fynla\Core\Models\User::lockForUpdate()->find($inviter->id);

                if ($inviter->spouse_id && $inviter->spouse_id !== $user->id) {
                    // Race: inviter has already linked elsewhere. Mark the
                    // permission as rejected so the UI stops surfacing it.
                    $permission->update(['status' => 'rejected', 'responded_at' => now()]);

                    return; // caller will detect this via fresh state below
                }

                $user->spouse_id = $inviter->id;
                $user->marital_status = 'married';
                $user->save();

                $inviter->spouse_id = $user->id;
                $inviter->marital_status = 'married';
                $inviter->save();

                // Create the reciprocal accepted permission (B → A direction).
                SpousePermission::updateOrCreate(
                    ['user_id' => $user->id, 'spouse_id' => $inviter->id],
                    ['status' => 'accepted', 'requested_at' => now(), 'responded_at' => now()]
                );

                // Create the reciprocal FamilyMember record on the invitee's
                // side. (At invitation time we only created it on the
                // inviter's side; we wait until accept to mirror it here, so
                // a never-accepted invitation leaves no FamilyMember row in
                // the invitee's account.)
                $inviterNameParts = explode(' ', (string) $inviter->name);
                $inviterFirstName = $inviterNameParts[0] ?? '';
                $inviterLastName = implode(' ', array_slice($inviterNameParts, 1)) ?: '';

                FamilyMember::create([
                    'user_id' => $user->id,
                    'household_id' => $user->household_id,
                    'linked_user_id' => $inviter->id,
                    'relationship' => 'spouse',
                    'first_name' => $inviterFirstName,
                    'last_name' => $inviterLastName,
                    'date_of_birth' => $inviter->date_of_birth,
                    'gender' => $inviter->gender,
                    'national_insurance_number' => $inviter->national_insurance_number,
                    'annual_income' => $inviter->employment_income ?? 0,
                    'is_dependent' => false,
                    'name' => $inviter->name,
                ]);

                $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $inviter->id);
            }
        });

        $permission = $permission->fresh();

        // Detect race-cancel: if the link finalisation hit the inviter-also-
        // linked-elsewhere branch, the transaction marked the permission as
        // rejected. Surface that back to the caller.
        if ($permission->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'The inviter has linked another spouse — the invitation can no longer be accepted',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission granted successfully',
            'data' => ['permission' => $permission],
        ]);
    }

    /**
     * Reject a permission request.
     *
     * POST /api/spouse-permission/reject
     *
     * G-4-b slice 3 H-3: parallels the new accept() flow — the invitee can
     * reject a pending invitation even before any link has been established
     * (so no spouse_id pre-check). Rejection never mutates either user's
     * spouse_id / marital_status.
     */
    public function reject(Request $request): JsonResponse
    {
        $user = $request->user();

        $permission = SpousePermission::where('spouse_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No pending permission request found',
            ], 404);
        }

        $permission->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission request rejected',
            'data' => ['permission' => $permission->fresh()],
        ]);
    }

    /**
     * Revoke permission (can be done by either spouse)
     *
     * DELETE /api/spouse-permission/revoke
     */
    public function revoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->spouse_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a linked spouse',
            ], 422);
        }

        // Find the permission (either direction)
        $permission = SpousePermission::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('spouse_id', $user->spouse_id);
        })->orWhere(function ($query) use ($user) {
            $query->where('user_id', $user->spouse_id)
                ->where('spouse_id', $user->id);
        })->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No permission found to revoke',
            ], 404);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission revoked successfully',
        ]);
    }
}
