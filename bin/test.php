#!/usr/bin/env php
<?php
require_once(__DIR__ . '/../vendor/autoload.php');

/**
 * Shows command line options help *
 */
function showOptionsHelp()
{
    global $scriptName;

    echo "
./$scriptName options
Options:
-s|--start -c|--config path-to-YAML-config
    Starts daemon at port read from YAML configuration file.
-t|--stop
    Stops daemon and closes all active connections.
-a|--status
    Checks if daemon is running.
-r|--restart
    Restarts daemon at port it was running before.
-h|--help
    Shows this help.
    ";

}

$pidPath = '/tmp/my_php_daemon.pid';

$shortOptions = 'starob:h';
$longOptions = [
    'start',        // -s
    'stop',         // -t
    'status',       // -a
    'restart',      // -r
    'daemon-off',   // -o
    'bind:',         // -b
    'help',         // -h
];

$options = getopt($shortOptions, $longOptions);

if (!$options) {
    echo "[ERROR] Wrong command line arguments.";
    showOptionsHelp();
    exit(-1);
}
$validator = new \HW3\Validator();
$logger = new \HW3\Logger($validator);
$daemon = new \HW3\SocketServer($validator, $logger, $pidPath);

foreach ($options as $key => $value) {
    switch ($key) {
        case 's':
        case 'start':
            $daemon->start(true);
            break;
        case 't':
        case 'stop':
            $daemon->stop();
            break;
        case 'a':
        case 'status':
            if (!isDaemonActive($daemonPidFilename)) {
                echo "Daemon is not running.\n";
                exit(-1);
            }

            $pid = file_get_contents($daemonPidFilename);
            $port = file_get_contents($daemonPortFilename);

            echo "Daemon is running with PID $pid at port $port.\n";
            break;
        case 'r':
            if (!isDaemonActive($daemonPidFilename)) {
                echo "Daemon is not running.\n";
                exit(-1);
            }

            $pid = file_get_contents($daemonPidFilename);
            $port = file_get_contents($daemonPortFilename);

            posix_kill($pid, SIGHUP);
            break;
        case 'h':
        case 'help':
            showOptionsHelp();
            break;
        case 'c':
        case 'config':
            break;
        case 'o':
        case 'daemon-off':
            break;
    }
}