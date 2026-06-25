<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanOldReports extends Command
{
    protected $signature = 'reports:cleanup';
    protected $description = 'Delete generated report PDFs older than 48 hours';

    public function handle()
    {
        $disk = Storage::disk('local');
        $directories = ['reports/daily', 'reports/weekly', 'reports/monthly'];

        $now = Carbon::now();

        foreach ($directories as $dir) {
            if (!$disk->exists($dir)) continue;

            foreach ($disk->files($dir) as $file) {
                $lastModified = Carbon::createFromTimestamp(
                    $disk->lastModified($file)
                );

                if ($lastModified->diffInHours($now) >= 48) {
                    $disk->delete($file);
                }
            }
        }

        return 0;
    }
}
