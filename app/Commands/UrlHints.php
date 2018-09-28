<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class UrlHints extends Command
{
    protected $signature = 'url:hints {url : URL to check}';
    protected $description = 'Give gadgets hints according to software detected on provided URL';
    private $hints = array(
        'wp-statistics' => ['guzzle'],
    );

    public function handle(): void
    {
        $url = $this->argument('url');
        $softwareList = [];

        $this->task(
            "Getting software list",
            function () use ($url, &$softwareList) {
                $softwareList = makeDetectemFingerprint($url);
                return true;
            }
        );

        foreach ($softwareList as $software) {
            if (array_key_exists($software->name, $this->hints)) {
                $gadgetHints = $this->hints[$software->name];
                $this->info(
                    "Software $software->name has the following gadgets: " .
                    implode($gadgetHints)
                );
            }
        }
    }
}
