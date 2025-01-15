<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class sendInterestedCourseCategoryEmailCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-interested-course-category-email-cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("CRON JOB Running email 1");
        $url = 'http://192.168.0.75:8000/api/admin/cronCorseCategoryInterested';

        // Make the API call using GET (or POST if needed)
        try {
            $response = Http::get($url);

            // Check if the request was successful
            if ($response->successful()) {
                $this->info('API call was successful');
            } else {
                $this->error('API call failed with status: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('Error calling API: ' . $e->getMessage());
        }
    }
}
