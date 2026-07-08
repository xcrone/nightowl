<?php

namespace App\Domains\Apps\Notifications;

use App\Models\OrgInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an invitee's email when InviteOrgMember creates a new invitation
 * — an on-demand notification (Notification::route('mail', $email)), since
 * the invitee may not have a User account yet. Deliberately not
 * ShouldQueue: no queue infra is assumed for this api.
 */
class OrgInvitationReceived extends Notification
{
    public function __construct(public OrgInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $org = $this->invitation->org;
        $url = rtrim(config('nightowl.agent.dashboard_url'), '/').'/login';

        $message = (new MailMessage)
            ->subject("You've been invited to join {$org->name} on NightOwl");

        if ($this->invitation->invitedBy) {
            $message->line("{$this->invitation->invitedBy->name} has invited you to join {$org->name} on NightOwl.");
        } else {
            $message->line("You've been invited to join {$org->name} on NightOwl.");
        }

        return $message
            ->action('Log in to respond', $url)
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
