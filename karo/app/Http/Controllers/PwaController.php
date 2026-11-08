<?php

namespace App\Http\Controllers;

class PwaController extends Controller
{
    public function serviceWorker()
    {
        return response(file_get_contents(resource_path('pwa/sw.js')), 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => '/']);
    }

    public function manifest()
    {
        return response()->json([
            'name' => 'Karo — '.(setting('university_acronym') ?: setting('university_name')), 'short_name' => 'Karo', 'lang' => 'fr',
            'start_url' => url('/'), 'scope' => url('/').'/', 'display' => 'standalone', 'background_color' => '#F4F7FC', 'theme_color' => '#0B1F3A',
            'icons' => [['src' => asset('images/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'], ['src' => asset('images/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png']],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
