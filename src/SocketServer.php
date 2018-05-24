<?php

namespace HW3;
require_once(__DIR__ . '/../vendor/autoload.php');

use HW3\Exceptions\SocketServer\AcceptConnectionException;
use HW3\Exceptions\SocketServer\SocketBindException;
use HW3\Exceptions\SocketServer\SocketCreateException;
use HW3\Exceptions\SocketServer\SocketListenException;
use HW3\Exceptions\SocketServer\SocketServerException;
use HW3\Exceptions\SystemCalls\ForkException;
use HW3\Exceptions\Validator\ValidatorException;
use HW3\Interfaces\ConnectionHandlerInterface;
use HW3\Interfaces\FileSystemInterface;
use HW3\Interfaces\LoggerInterface;
use HW3\Interfaces\SocketServerInterface;
use HW3\Interfaces\SystemCallsInterface;
use HW3\Interfaces\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class SocketServer implements SocketServerInterface
{
    protected $validator;
    protected $fileSystem;
    protected $systemCalls;
    protected $logger;
    protected $pid;
    protected $port;
    protected $socket;
    protected $connections;
    protected $connectionHandler;
    protected static $configKeys = [
        'port',
        ];
    protected $configParams;

    public function __construct(
        $port,
        ConnectionHandlerInterface $connectionHandler,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        FileSystemInterface $fileSystem = null,
        SystemCallsInterface $systemCalls = null
    )
    {
        $this->connectionHandler = $connectionHandler;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->port = $port;

        if ($fileSystem === null) {
            $fileSystem = new FileSystem;
        }

        if ($systemCalls === null) {
            $systemCalls = new SystemCalls;
        }

        $this->fileSystem = $fileSystem;
        $this->systemCalls = $systemCalls;
    }

    public function createSocket($host = 'localhost') {
        if (($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            throw new SocketCreateException(socket_strerror(socket_last_error()));
        }

        if (socket_bind($this->socket, $host, $this->port) === false) {
            throw new SocketBindException(socket_strerror(socket_last_error()));
        }

        socket_set_nonblock($this->socket);
        $this->logger->log("Socket server opened listening socket at $host:$this->port");
    }

    public function closeSocket($socket) {
        $linger = [
            'l_linger' => 0,
            'l_onoff' => 1,
        ];

        socket_set_option($socket, SOL_SOCKET, SO_LINGER, $linger);
        socket_close($socket);
    }

    public function listen()
    {
        try {
            $this->createSocket();
        } catch (SocketCreateException | SocketBindException $e) {
            $this->closeSocket($this->socket);
            throw new SocketServerException("An error occurred \"{$e->getMessage()}\"");
        }


        if (socket_listen($this->socket, 5) === false) {
            throw new SocketListenException(socket_strerror(socket_last_error()));
        }
    }

    public function acceptConnections()
    {
        $connection = socket_accept($this->socket);

        if ($connection === false) {
            $this->systemCalls->sendSignals();
            return;
        }

        if ($connection > 0) {
            $this->logger->log('Connection accepted');
            $this->createConnectionHandler($connection);
        } else {
            throw new AcceptConnectionException(socket_strerror(socket_last_error($connection)));
        }
    }

    public function createConnectionHandler($connection)
    {
        $this->logger->log('Fork to create connection handler');

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new ForkException('Error occured while forking to create connection handler');
        }

        if ($pid === 0) {
            // child process
            $pid = getmypid();
            $this->closeSocket($this->socket);

            $this->logger->log("Connection handler with PID $pid created");
            socket_set_nonblock($connection);
            $this->connectionHandler->handle($connection);
            $this->closeSocket($connection);
            $this->logger->log("Connection handler with PID $pid terminated");
        } else {
            $this->connections[] = $pid;
        }
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function dropConnections()
    {
        if (!empty($this->connections)) {
            $this->logger->log("Starting to drop all active connections...");
            foreach ($this->connections as $pid) {
                $this->systemCalls->stopProcess($pid);
                $this->logger->log("Sent SIGTERM signal to connection $pid");
            }

            $this->logger->log("Waiting for connections to drop");
            while (count($this->connections)) {
                $pid = $this->systemCalls->waitAsync($status);
                if ($pid > 0) {
                    $this->logger->log("Connection $pid was closed with exit code $status");
                    unset($this->connections[array_search($pid, $this->connections)]);
                    $this->showActiveConnections();
                }
            }
        }
    }

    public function showActiveConnections()
    {
        $connectionsList = '';

        foreach ($this->connections as $p) {
            $connectionsList .= $p . ' ';
        }

        $activeConnectionsCount = count($this->connections);
        $message = "Active connections [$activeConnectionsCount]: $connectionsList";

        if ($activeConnectionsCount == 0) {
            $message = "No active connections";
        }

        $this->logger->log($message);
    }

    public function refreshConnections()
    {
        $pid = $this->systemCalls->waitSync($status);
        $this->logger->log("Received disconnect from client $pid");
        unset($this->connections[array_search($pid, $this->connections)]);
        $this->logger->log("client $pid disconnected with exit code $status");

        $this->showActiveConnections();
    }

    public function getParamsFromConfig($configPath)
    {
        try {
            $this->validator->validatePath($configPath);
        } catch (ValidatorException $e) {
            throw new SocketServerException("An error occurred \"{$e->getMessage()}\"");
        }

        try {
            $this->configParams = Yaml::parseFile($configPath);
        } catch (ParseException $e) {
            $this->logger->error("Unable to parse the YAML string: {$e->getMessage()}");
            exit(-1);
        }

        try {
            validateConfigParams($this->configParams, self::$configKeys);
        } catch (ValidatorException $e) {
            $this->logger->error("An error occurred \"{$e->getMessage()}\"");
        }
    }
}