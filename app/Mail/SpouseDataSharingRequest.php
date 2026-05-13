<?php

declare(strict_types=1);

namespace App\Mail;

use Fynla\Core\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an existing Fynla user when another user invites them to link as
 * spouse + share financial data. The recipient must explicitly accept the
 * invitation in their own account before any linkage or data sharing is
 * established. (G-4-b slice 3 H-2/H-3: replaces the SpouseAccountLinked email
 * for the pre-acceptance state, since accounts are no longer auto-linked.)
 */
class SpouseDataSharingRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $invitee,
        public User $invitedBy
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Spouse Invitation on Fynla — Action Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.spouse-data-sharing-request',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
