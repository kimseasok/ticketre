<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('queue:work --stop-when-empty')->everyFiveMinutes();
        $schedule->command('meilisearch:health-check')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->when(fn () => config('scout.driver') === 'meilisearch');
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        $schedule->command('horizon:health-check')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/../Console/Commands');
        require base_path('routes/console.php');
    }
}
