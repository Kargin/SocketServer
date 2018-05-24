#!/usr/bin/env php
<?php

require_once 'vendor/autoload.php';
use Kargin\Bracketeer;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Handles various process signals sent with posix_kill
 *
 * @param $signal
 */
function signalHandler($signal) {
    global $childPids;
    global $isClientActive;
    global $daemonPid;
    global $daemonSocket;
    global $isServerActive;
    global $gotSigHup;

    $pid = getmypid();

    switch($signal) {
        case SIGTERM:
        case SIGINT:
            if ($pid == $daemonPid) {
                // main daemon
                logMessage("[INFO] Received signal SIGTERM.");

                if (!empty($childPids)) {
                    logMessage("[INFO] Starting to close all active connections...");
                    foreach ($childPids as $p) {
                        posix_kill($p, SIGTERM);
                        logMessage("[INFO] Sent SIGTERM signal to $p");
                    }

                    logMessage("[INFO] Starting to wait for childs to finish.");
                    while (count($childPids)) {
                        $childPid = pcntl_waitpid(-1, $status, WNOHANG);
                        if ($childPid > 0) {
                            logMessage("[INFO] $childPid finished with exit code $status");
                            unset($childPids[array_search($childPid, $childPids)]);
                        }
                    }

                }

                $linger = [
                    'l_linger' => 0,
                    'l_onoff' => 1,
                ];

                socket_set_option($daemonSocket, SOL_SOCKET, SO_LINGER, $linger);
                socket_close($daemonSocket);

                exit();
            } else {
                // client connections
                logMessage("[INFO] [$pid] Received signal SIGTERM.");
                $isClientActive = false;
            }
            break;
        case SIGHUP:
            logMessage("[INFO] Received signal SIGHUP.");

            if (!empty($childPids)) {
                logMessage("[INFO] Starting to close all active connections...");
                foreach ($childPids as $p) {
                    posix_kill($p, SIGTERM);
                    logMessage("[INFO] Sent SIGTERM signal to $p");
                }

                logMessage("[INFO] Starting to wait for childs to finish.");

                while (count($childPids)) {
                    $childPid = pcntl_waitpid(-1, $status, WNOHANG);
                    if ($childPid > 0) {
                        logMessage("[INFO] $childPid finished with exit code $status");
                        unset($childPids[array_search($childPid, $childPids)]);
                    }
                }
            }

            $linger = [
                'l_linger' => 0,
                'l_onoff' => 1,
            ];

            socket_set_option($daemonSocket, SOL_SOCKET, SO_LINGER, $linger);
            socket_close($daemonSocket);
            $gotSigHup = true;
            $isServerActive = false;

            break;
        case SIGCHLD:
            $childPid = pcntl_waitpid(-1, $status);
            logMessage("[INFO] Received signal SIGCHLD from $childPid.");
//            unset($childConnections[$childPid]);
            unset($childPids[array_search($childPid, $childPids)]);
            logMessage("[INFO] Client $childPid disconnected with exit code $status.");
            $str = '';

            foreach ($childPids as $p) {
                $str .= $p . ' ';
            }

            $activeConnectionsCount = count($childPids);
            $message = "[INFO] Daemon now has $activeConnectionsCount active connections: $str";

            if ($activeConnectionsCount == 1) {
                $message = "[INFO] Daemon now has $activeConnectionsCount active connection: $str";
            } elseif ($activeConnectionsCount == 0) {
                $message = "[INFO] Daemon now has no connections.";
            }

            logMessage($message);

            break;
    }
}

/**
 * Forks for each client connection and runs interact method
 *
 * @param $serverSocket
 * @param $connection
 */
function handleClient($serverSocket, $connection) {
    global $isServerActive;
    global $childPids;

    $pid = pcntl_fork();

    if ($pid == -1) {
        logMessage("[ERROR] Something went wrong while handling client.");
        exit();
    } elseif ($pid == 0) {
        // child process
        $isServerActive = false;
        $pid = getmypid();
        $linger = [
            'l_linger' => 0,
            'l_onoff' => 1,
        ];
        socket_set_option($serverSocket, SOL_SOCKET, SO_LINGER, $linger);
        socket_close($serverSocket);
        logMessage("[INFO] [$pid] Client connected.");
        interact($connection);
        socket_set_option($connection, SOL_SOCKET, SO_LINGER, $linger);
        socket_close($connection);
        logMessage("[INFO] [$pid] Client disconnected.");
    } else {
        $childPids[] = $pid;
//        $childConnections[$pid] = $connection;
        socket_close($connection);
    }
}

/**
 * Interacts with client using Bracketeer composer library
 *
 * @param $socket
 */
function interact($socket)
{
    global $isClientActive;
    $bracketeer = new Bracketeer();
    $pid = getmypid();
    $msg = "\nWelcome to the Bracketeer composer library server.\n" .
        "Type any number of brackets to test if they are balanced and press ENTER.\n" .
        "To quit, type 'quit'.\n";
    socket_write($socket, $msg, strlen($msg));

    while ($isClientActive) {
        // read client input
        $isBalanced = false;
        $hasException = false;
        $output = "False. Entered string is NOT balanced.\n";

        $buf = socket_read($socket, 2048, PHP_BINARY_READ);

        if (empty($buf)) {
            pcntl_signal_dispatch();
            usleep(100);
        } elseif ($buf === false) {
            logMessage("[ERROR] [$pid] socket_read() failed. Reason: " . socket_strerror(socket_last_error($socket)));
            break;
        } else {
            if (!$buf = trim($buf)) {
                continue;
            }

            if ($buf == 'quit') {
                break;
            }

            // reverse client input and send back
            logMessage("[INFO] [$pid] Client entered: " . $buf);

            try {
                $isBalanced = $bracketeer->isBalanced($buf);
            } catch (Exception $e) {
                $hasException = true;
                $output = get_class($e) . ":\n" . $e->getMessage() . PHP_EOL;
            }

            if (!$hasException && $isBalanced) {
                $output = "True. Entered string is balanced!\n";
            }

            if (socket_write($socket, $output, strlen($output)) === false) {
                logMessage("[ERROR] [$pid] socket_write() failed. Reason: " . socket_strerror(socket_last_error($socket)));
                break;
            }

            logMessage("[INFO] [$pid] Server answered: " . $output);
        }
    }
}

/**
 * Checks if daemon is active and returns true.
 *
 * @param $pidFile
 * @return bool
 */
function isDaemonActive($pidFile) {
    if (is_file($pidFile)) {
        $pid = file_get_contents($pidFile);
        if (posix_kill($pid, 0)) {
            // daemon is already running
            return true;
        } else {
            // pid file is present, but there is no process
            if(!unlink($pidFile)) {
                echo "ERROR: could not delete $pidFile";
                exit(-1);
            }
        }
    }
    return false;
}

/**
 * Starts socket server daemon
 *
 * @param $configFilepath
 * @param $PidFilename
 * @param $PortFilename
 * @param $host
 */
function start($configFilepath, $PidFilename, $PortFilename, $host) {
    global $daemonConfigParameters;
    global $daemonConfigKeys;
    global $isServerActive;
    global $daemonPid;
    global $daemonPort;
    global $daemonSocket;
    global $gotSigHup;
    global $isDaemon;

    $gotSigHup = false;
    $isServerActive = true;

    try {
        $daemonConfigParameters = Yaml::parseFile($configFilepath);
    } catch (ParseException $e) {
        logMessage("Unable to parse the YAML string: {$e->getMessage()}");
        echo sprintf("Unable to parse the YAML string: %s\n", $e->getMessage());
        exit(-1);
    }

    validateConfigParameters($daemonConfigParameters, $daemonConfigKeys);

    $daemonPort = $daemonConfigParameters['port'];

    validatePort($daemonPort);

    if ($isDaemon) {
        // become daemon
        $pid = pcntl_fork();

        if ($pid == -1)  {
            echo "ERROR: fork failure!\n";
            exit(-1);
        } elseif ($pid) {
            // parent
            exit();
        } else {
            // child becomes daemon
            if (file_put_contents($PidFilename,  getmypid()) === false) {
                echo "[ERROR] Could not write PID to $PidFilename\n";
                exit(-1);
            }

            if (file_put_contents($PortFilename, $daemonPort) === false) {
                echo "[ERROR] Could not write port to $PortFilename\n";
                exit(-1);
            }

            posix_setsid();
            chdir('/');
            $daemonPid = file_get_contents($PidFilename);
        }
    } else {
        if (file_put_contents($PidFilename,  getmypid()) === false) {
            echo "[ERROR] Could not write PID to $PidFilename\n";
            exit(-1);
        }

        if (file_put_contents($PortFilename, $daemonPort) === false) {
            echo "[ERROR] Could not write port to $PortFilename\n";
            exit(-1);
        }

        $daemonPid = file_get_contents($PidFilename);
    }

    if (($daemonSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
        echo sprintf("socket_create() failed: reason: %s\n", socket_strerror(socket_last_error()));
        exit(-1);
    }

    if (socket_bind($daemonSocket, $host, $daemonPort) === false) {
        echo sprintf("socket_bind() failed: reason: %s\n", socket_strerror(socket_last_error($daemonSocket)));
        exit(-1);
    }

    if (socket_listen($daemonSocket, 5) === false) {
        echo sprintf("socket_listen() failed: reason: %s\n", socket_strerror(socket_last_error($daemonSocket)));
        exit(-1);
    }

    socket_set_nonblock($daemonSocket);
    logMessage("[INFO] Socket server started with PID $daemonPid at port $daemonPort");
    echo "Socket server started with PID $daemonPid at port $daemonPort\n";
    echo "Waiting for clients to connect...\n";

    while ($isServerActive)
    {
        $clientConnection = socket_accept($daemonSocket);
        if ($clientConnection === false) {
            pcntl_signal_dispatch();
            usleep(100);
        } elseif ($clientConnection > 0) {
            socket_set_nonblock($clientConnection);
            handleClient($daemonSocket, $clientConnection);
        } else {
            logMessage("[ERROR] " . socket_strerror(socket_last_error($clientConnection)));
            exit(-1);
        }
    }

    if ($gotSigHup) {
        start($configFilepath, $PidFilename, $PortFilename, $host);
    }
}

/**
 * Stops socket server daemon
 *
 * @param $daemonPidFile
 * @param $daemonPortFile
 */
function stop($daemonPidFile, $daemonPortFile) {
    $header = '[' . file_get_contents($daemonPidFile) . '] ';
    
    if (!posix_kill(file_get_contents($daemonPidFile) , SIGTERM)) {
        echo $header . "ERROR: something went wrong while stopping daemon. posix_kill returned false.\n";
        exit(-1);
    }

    if(!unlink($daemonPidFile)) {
        echo "ERROR: could not delete $daemonPidFile.";
        exit(-1);
    }

    if(!unlink($daemonPortFile)) {
        echo "ERROR: could not delete $daemonPortFile.";
        exit(-1);
    }

    echo $header . "Daemon was successfully stopped.\n";
    logMessage("[INFO] Daemon was successfully stopped.");
}

/**
 * Writes given message to daemon log file
 *
 * @param $message
 */
function logMessage($message) {
    global $daemonLogFilename;
    $logMessage = date("Y.m.d H:i:s ") . $message;
    if (file_put_contents($daemonLogFilename, $logMessage . PHP_EOL, FILE_APPEND) === false) {
        echo "ERROR: something went wrong while writing to $daemonLogFilename\n";
    }
}

/**
 * Validates keys in given array using array of allowed configuration keys
 *
 * @param $configParameters
 * @param $configAllowedKeys
 * @return bool
 */
function validateConfigParameters($configParameters, $configAllowedKeys) {
    $hasErrors = false;
    $unknownKeys = [];

    foreach ($configParameters as $key => $value) {
        if (!in_array($key, $configAllowedKeys)) {
            $unknownKeys[] = $key;
            $hasErrors = true;
        }
    }

    if ($hasErrors) {
        echo sprintf('[ERROR] Following keys in configuration file are unknown: %s',
            array_walk(
                    $unknownKeys,
                    function ($item) {
                        echo "$item ";
                    })
        );
        exit(-1);
    }
}

/**
 * Validates port number
 *
 * @param $port
 */
function validatePort($port) {
    if (!preg_match('/^\d+$/', $port)) {
        echo "ERROR: invalid port number format. You must enter only digits.\n";
        exit(-1);
    }

    if ($port <= 1024 && $port > 65535) {
        echo "ERROR: port number must be > 1024 and < 65536.\n";
        exit(-1);
    }
}

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
    Starts daemon at port read taken from YAML configuration file.
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

$daemonDirectory = "/tmp";
$configFilepath = '';
$daemonConfigParameters = [];
$daemonConfigKeys = [
        'port',
];
$scriptName = pathinfo($argv[0], PATHINFO_FILENAME);
$options = [];
$daemonPidFilename = sprintf('%s/%s.pid', $daemonDirectory, $scriptName);
$daemonPortFilename = sprintf('%s/%s.port', $daemonDirectory, $scriptName);
$daemonLogFilename = sprintf('%s/%s.log', $daemonDirectory, $scriptName);
$daemonPid = null;
$daemonHost = 'localhost';
$daemonPort = null;
$daemonSocket = null;
$childPids = [];
$childConnections = [];
$isServerActive = true;
$isClientActive = true;
$gotSigHup = false;
$isDaemon = true;

error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');
pcntl_signal(SIGCHLD, 'signalHandler');
pcntl_signal(SIGHUP, 'signalHandler');

$shortOptions = 'starc:ob:h';
$longOptions = [
    'start',        // -s
    'stop',         // -t
    'status',       // -a
    'restart',      // -r
    'config:',      // -c
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

foreach ($options as $key => $value) {
    switch ($key) {
        case 's':
        case 'start':
            if (isDaemonActive($daemonPidFilename)) {
                echo sprintf("ERROR: Daemon is already running with PID %s", file_get_contents($daemonPidFilename));
                exit(-1);
            }

            if (!isset($options['c']) && !isset($options['config'])) {
                echo "[ERROR] Missing configuration filepath.\n";
                echo "Use ./$scriptName -s|--start -c|--config path-to-YAML-config [-o|--daemon-off]\n";
                exit(-1);
            }

            $filePathToCheck = isset($options['c']) ? $options['c'] : $options['config'];

            if (!file_exists($filePathToCheck)) {
                echo sprintf("[ERROR] File \"%s\" does not exist.\n", $filePathToCheck);
                exit(-1);
            }

            $configFilepath = realpath($filePathToCheck);

            if (isset($options['o']) || isset($options['daemon-off'])) {
                $isDaemon = false;
            }

            if (isset($options['b']) || isset($options['bind'])) {
                $daemonHost = isset($options['b']) ? $options['b'] : $options['bind'];
            }

            start($configFilepath, $daemonPidFilename, $daemonPortFilename, $daemonHost);
            break;
        case 't':
        case 'stop':
            if (!isDaemonActive($daemonPidFilename)) {
                echo "ERROR: Nothing to stop. Daemon is not running.\n";
                exit(-1);
            }

            stop($daemonPidFilename, $daemonPortFilename);
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

