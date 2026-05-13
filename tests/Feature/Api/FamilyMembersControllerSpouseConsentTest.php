<?php

declare(strict_types=1);

use App\Mail\SpouseDataSharingRequest;
use Fynla\Core\Models\FamilyMember;
use Fynla\Core\Models\Household;
use Fynla\Core\Models\SpousePermission;
use Fynla\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->household = Household::factory()->create();

    $this->inviter = User::factory()->create([
        'household_id' => $this->household->id,
        'first_name' => 'Alice',
        'surname' => 'Inviter',
        'email' => 'alice-inviter@test.com',
        'annual_employment_income' => 50000,
        'address_line_1' => '1 Alice Street',
        'city' => 'London',
        'postcode' => 'SW1 1AA',
        'marital_status' => 'single',
    ]);

    // The invitee already has an account, an income, an address, a marital
    // status, and is not linked to anyone. The H-3 invariants check that
    // none of these are mutated by an unsolicited spouse-invite from Alice.
    $this->invitee = User::factory()->create([
        'household_id' => Household::factory()->create()->id,
        'first_name' => 'Bob',
        'surname' => 'Invitee',
        'email' => 'bob-invitee@test.com',
        'annual_employment_income' => 80000,
        'address_line_1' => '99 Bob Avenue',
        'city' => 'Manchester',
        'postcode' => 'M1 1BB',
        'marital_status' => 'single',
        'spouse_id' => null,
    ]);
});

describe('G-4-b slice 3 H-3 — adding existing user as spouse does NOT auto-link', function () {

    it('leaves the invitee.spouse_id NULL when an unsolicited spouse invite arrives', function () {
        $response = $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
                'annual_income' => 1,
            ]);

        $response->assertStatus(201);

        $this->invitee->refresh();
        expect($this->invitee->spouse_id)->toBeNull();
        expect($this->invitee->marital_status)->toBe('single');
    });

    it('does NOT mutate the invitee.annual_employment_income — the marquee CVE shape', function () {
        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
                // Attacker tries to tamper with the invitee's stated income
                // by sending a high value in the family-members payload.
                'annual_income' => 9999999,
            ]);

        $this->invitee->refresh();
        expect((float) $this->invitee->annual_employment_income)->toBe(80000.0);
    });

    it('does NOT overwrite the invitee.address fields with the inviter address', function () {
        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);

        $this->invitee->refresh();
        expect($this->invitee->address_line_1)->toBe('99 Bob Avenue');
        expect($this->invitee->city)->toBe('Manchester');
        expect($this->invitee->postcode)->toBe('M1 1BB');
    });

    it('does NOT auto-accept SpousePermission rows — invitation is pending', function () {
        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);

        $invitePerm = SpousePermission::where('user_id', $this->inviter->id)
            ->where('spouse_id', $this->invitee->id)
            ->first();
        expect($invitePerm)->not->toBeNull();
        expect($invitePerm->status)->toBe('pending');

        // The reciprocal direction permission must NOT be created at invite
        // time — only after the invitee accepts.
        $reverse = SpousePermission::where('user_id', $this->invitee->id)
            ->where('spouse_id', $this->inviter->id)
            ->first();
        expect($reverse)->toBeNull();
    });

    it('does NOT leave the inviter.spouse_id set pre-acceptance', function () {
        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);

        $this->inviter->refresh();
        expect($this->inviter->spouse_id)->toBeNull();
        expect($this->inviter->marital_status)->toBe('single');
    });

    it('does NOT create a reciprocal FamilyMember on the invitee side until accepted', function () {
        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);

        // Inviter's side: FamilyMember row exists (so their UI shows
        // "spouse: Bob (pending)").
        $inviterSide = FamilyMember::where('user_id', $this->inviter->id)
            ->where('relationship', 'spouse')
            ->first();
        expect($inviterSide)->not->toBeNull();

        // Invitee's side: nothing.
        $inviteeSide = FamilyMember::where('user_id', $this->invitee->id)
            ->where('relationship', 'spouse')
            ->first();
        expect($inviteeSide)->toBeNull();
    });

    it('sends a SpouseDataSharingRequest email (not SpouseAccountLinked) to the invitee', function () {
        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);

        Mail::assertSent(SpouseDataSharingRequest::class, function ($mail) {
            return $mail->hasTo('bob-invitee@test.com');
        });

        Mail::assertNotSent(\App\Mail\SpouseAccountLinked::class);
    });

    it('returns invitation_pending=true in the response payload', function () {
        $response = $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);

        expect($response->json('data.linked'))->toBeFalse();
        expect($response->json('data.invitation_pending'))->toBeTrue();
    });

    it('is idempotent — a duplicate invite reuses the existing pending permission', function () {
        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);

        $countBefore = SpousePermission::where('user_id', $this->inviter->id)
            ->where('spouse_id', $this->invitee->id)
            ->count();

        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);

        $countAfter = SpousePermission::where('user_id', $this->inviter->id)
            ->where('spouse_id', $this->invitee->id)
            ->count();

        expect($countBefore)->toBe(1);
        expect($countAfter)->toBe(1);
    });
});

describe('G-4-b slice 3 H-3 — invitee accept finalises the link', function () {

    beforeEach(function () {
        // Send the invitation first.
        $this->actingAs($this->inviter)
            ->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'first_name' => 'Bob',
                'last_name' => 'Invitee',
                'email' => 'bob-invitee@test.com',
                'date_of_birth' => '1985-01-01',
            ]);
    });

    it('sets spouse_id on BOTH users only when the invitee accepts', function () {
        $response = $this->actingAs($this->invitee)
            ->postJson('/api/spouse-permission/accept');

        $response->assertStatus(200);

        $this->invitee->refresh();
        $this->inviter->refresh();
        expect($this->invitee->spouse_id)->toBe($this->inviter->id);
        expect($this->inviter->spouse_id)->toBe($this->invitee->id);
        expect($this->invitee->marital_status)->toBe('married');
        expect($this->inviter->marital_status)->toBe('married');
    });

    it('creates the reciprocal accepted SpousePermission on accept', function () {
        $this->actingAs($this->invitee)
            ->postJson('/api/spouse-permission/accept');

        $forward = SpousePermission::where('user_id', $this->inviter->id)
            ->where('spouse_id', $this->invitee->id)
            ->first();
        $reverse = SpousePermission::where('user_id', $this->invitee->id)
            ->where('spouse_id', $this->inviter->id)
            ->first();

        expect($forward->status)->toBe('accepted');
        expect($reverse)->not->toBeNull();
        expect($reverse->status)->toBe('accepted');
    });

    it('creates the reciprocal FamilyMember on the invitee side only at accept', function () {
        $this->actingAs($this->invitee)
            ->postJson('/api/spouse-permission/accept');

        $inviteeFamilyMember = FamilyMember::where('user_id', $this->invitee->id)
            ->where('relationship', 'spouse')
            ->first();

        expect($inviteeFamilyMember)->not->toBeNull();
        expect($inviteeFamilyMember->linked_user_id)->toBe($this->inviter->id);
    });

    it('allows the invitee to reject a pending invite without any linkage occurring', function () {
        $response = $this->actingAs($this->invitee)
            ->postJson('/api/spouse-permission/reject');

        $response->assertStatus(200);

        $this->invitee->refresh();
        $this->inviter->refresh();
        expect($this->invitee->spouse_id)->toBeNull();
        expect($this->inviter->spouse_id)->toBeNull();

        $forward = SpousePermission::where('user_id', $this->inviter->id)
            ->where('spouse_id', $this->invitee->id)
            ->first();
        expect($forward->status)->toBe('rejected');
    });
});
