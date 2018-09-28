<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use GuzzleHttp\Client;

class UrlDownload extends Command
{
    protected $signature = 'url:download {url : URL to download}';
    protected $description = 'Download the software detected by detectem from provided url.
    Initial support only for Wordpress sites.';

    public function isWordpress($softwareList)
    {
        foreach ($softwareList as $software) {
            if ($software->name === 'wordpress') {
                return true;
            }
        }

        return false;
    }

    public function handle(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->error("You need to install php-zip package first!");
            return;
        }
        $url = $this->argument('url');
        $softwareList = [];

        $this->task(
            "Getting software list",
            function () use ($url, &$softwareList) {
                $softwareList = makeDetectemFingerprint($url);
                return true;
            }
        );

        // Create temporary directory to store plugins code
        $tempdir = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempdir)) {
            unlink($tempdir);
        }
        mkdir($tempdir);

        if (!$this->isWordpress($softwareList)) {
            $this->error('Sorry, only Wordpress sites are supported at the moment.');
            return;
        }

        foreach ($softwareList as $software) {
            // Deal only with wordpress plugins
            if (strpos($software->homepage, 'https://wordpress.org/plugins/') !== 0) {
                continue;
            }

            $this->task(
                "Getting source code for plugin " . $software->name,
                function () use ($tempdir, $software) {
                    $client = new Client();
                    $response = $client->get($software->homepage);
                    $responseText = $response->getBody()->getContents();

                    $doc = new \DOMDocument();
                    @$doc->loadHTML($responseText);
                    $xpath = new \DOMXpath($doc);
                    $downloadLinkNode = $xpath->query(
                        "//a[contains(@class,'plugin-download')]/@href"
                    )[0] ?? null;

                    if (!$downloadLinkNode) {
                        return false;
                    }

                    $downloadLink = $downloadLinkNode->value;
                    preg_match("#/([^/]+)\.zip$#", $downloadLink, $matches);
                    $softwareVersion = $matches[1];

                    $path = "$tempdir/$softwareVersion.zip";
                    $response = $client->get($downloadLink, ['sink' => $path]);

                    $zipArchive = new \ZipArchive;
                    $result = $zipArchive->open($path);
                    if ($result === true) {
                        $zipArchive->extractTo("$tempdir/$softwareVersion");
                        $zipArchive->close();
                    }

                    return true;
                }
            );
        }

        $this->info("Everything is fine, access your files at $tempdir");
    }
}
