<?php

namespace App\Console;

use App\Console\Commands\CleanLibraryCommand;
use App\Console\Commands\ClearCacheCommand;
use App\Console\Commands\ClearLibraryCommand;
use App\Console\Commands\DeleteImagesCommand;
use App\Console\Commands\DownloadStreamCommand;
use App\Console\Commands\PlayBackInfoCommand;
use App\Console\Commands\RebuildLibraryCommand;
use App\Console\Commands\RemoveItemCommand;
use App\Console\Commands\SaveItemCommand;
use App\Console\Commands\TestCommand;
use App\Console\Commands\UpdateItemCommand;
use App\Console\Commands\UpdateLibraryCommand;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {}
}
