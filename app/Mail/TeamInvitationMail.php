<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $workspaceName,
        public readonly string $role,
        public readonly string $invitationUrl,
        public readonly \DateTimeInterface $expiresAt,
    ) {}

    public function build(): self
    {
        return $this->subject(__('auth.team_invitation_subject', ['workspace' => $this->workspaceName]))
            ->view('emails.team-invitation')
            ->with([
                'workspaceName' => $this->workspaceName,
                'role' => $this->role,
                'invitationUrl' => $this->invitationUrl,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
