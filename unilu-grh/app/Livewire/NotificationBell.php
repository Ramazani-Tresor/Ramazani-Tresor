<?php

namespace App\Livewire;

use Livewire\Component;

/** Cloche des notifications internes (actualisée toutes les 60 s). */
class NotificationBell extends Component
{
    public function markRead(string $id): void
    {
        auth()->user()->unreadNotifications()->whereKey($id)->update(['read_at' => now()]);
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'count' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->latest()->limit(6)->get(),
        ]);
    }
}
