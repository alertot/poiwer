<?php

use GuzzleHttp\Client;

define('cli_reset', "\033[0m");
define('cli_bold', "\033[1m");
define('cli_clreol', "\033[K");

define('cli_black', "\033[30m");
define('cli_red', "\033[31m");
define('cli_green', "\033[32m");
define('cli_yellow', "\033[33m");
define('cli_blue', "\033[34m");
define('cli_magenta', "\033[35m");
define('cli_cyan', "\033[36m");
define('cli_white', "\033[37m");
define('cli_black_bg', "\033[40m");
define('cli_red_bg', "\033[41m");
define('cli_green_bg', "\033[42m");
define('cli_yellow_bg', "\033[43m");
define('cli_blue_bg', "\033[44m");
define('cli_magenta_bg', "\033[45m");
define('cli_cyan_bg', "\033[46m");
define('cli_white_bg', "\033[47m");

define('cli_error', "\033[41;30m" . cli_clreol);
define('cli_warning', "\033[43;30m" . cli_clreol);
define('cli_info', "\033[44;30m" . cli_clreol);
define('cli_success', "\033[42;30m" . cli_clreol);

function getFiles($path)
{
    $directory_iterator = new RecursiveDirectoryIterator($path);
    foreach (new RecursiveIteratorIterator($directory_iterator) as $file) {
        if (strpos($file, ".php")) {
            yield $file->getPathname();
        }
    }
}


function openInEditor($filepath, $line_number)
{
    $descriptors = array(
        array('file', '/dev/tty', 'r')
    );

    $process = proc_open(
        'vim +' . $line_number . ' ' . $filepath,
        $descriptors,
        $pipes
    );

    while (true) {
        if (proc_get_status($process)['running'] === false) {
            break;
        }
    }
}


function deleteLastSearch($menu)
{
    $flag = false;

    foreach ($menu->getItems() as $item) {
        if ($flag) {
            $menu->removeItem($item);
        }
        if ($item->getText() === 'Search method anywhere') {
            $flag = true;
        }
    }
}

function makeDetectemFingerprint($url)
{
    $client = new Client();
    $response = $client->request(
        'POST',
        'http://localhost:5723/detect',
        [
            'form_params' => ['url' => $url, 'metadata' => '1']
        ]
    );

    return json_decode($response->getBody()->getContents());
}
