<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Events\SendOtpMember::class => [
        \App\Listeners\SendOtpToMemberListener::class,
        ],
    ];

    /**
     * Activer l'auto-découverte des events dans app/Events
     */
    public function shouldDiscoverEvents()
    {
        return true;
    }

    /**
     * Définir où Laravel doit chercher les events à découvrir
     */
    protected function discoverEventsWithin()
    {
        return [
            app_path('Events'),
        ];
    }

    public function boot()
    {
        parent::boot();
    }
}
