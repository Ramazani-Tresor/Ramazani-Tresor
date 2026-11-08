<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Notification interne (base de données) et, si activée, par courrier électronique. */
class AgentNotification extends Notification
{
    use Queueable;

    public function __construct(public string $event, public string $title, public string $body, public ?string $url = null) {}

    public function via(object $notifiable): array
    {
        $channels = $notifiable instanceof User ? ['database'] : [];
        if (config('grh.mail_notifications') && $notifiable->routeNotificationFor('mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)
            ->subject('['.(Settings::get('university_acronym') ?: 'UNILU').'-GRH] '.$this->title)
            ->greeting('Bonjour,')
            ->line($this->body);
        if ($this->url) {
            $m->action('Consulter dans UNILU-GRH', $this->url);
        }

        return $m->line('Message automatique : merci de ne pas y répondre.');
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
