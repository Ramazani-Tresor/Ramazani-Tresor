<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications', ['notifications' => $request->user()->notifications()->latest()->paginate(30)]);
    }

    public function markAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Toutes les notifications sont marquées comme lues.');
    }
}
