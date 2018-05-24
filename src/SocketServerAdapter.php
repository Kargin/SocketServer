<?php

namespace HW3;


use HW3\Exceptions\Worker\WorkerException;
use HW3\Exceptions\SocketServer\SocketServerException;
use HW3\Interfaces\FileSystemInterface;
use HW3\Interfaces\LoggerInterface;
use HW3\Interfaces\SocketServerInterface;
use HW3\Interfaces\SystemCallsInterface;
use HW3\Interfaces\ValidatorInterface;
use HW3\Interfaces\WorkerInterface;

class SocketServerAdapter implements WorkerInterface
{
    protected $validator;
    protected $fileSystem;
    protected $systemCalls;
    protected $logger;
    protected $socketServer;

    public function __construct(
        ValidatorInterface $validator,
        LoggerInterface $logger,
        SocketServerInterface $socketServer,
        FileSystemInterface $fileSystem = null,
        SystemCallsInterface $systemCalls = null
    )
    {
        $this->validator = $validator;
        $this->logger = $logger;
        $this->socketServer = $socketServer;

        if ($fileSystem === null) {
            $fileSystem = new FileSystem;
        }

        if ($systemCalls === null) {
            $systemCalls = new SystemCalls;
        }

        $this->fileSystem = $fileSystem;
        $this->systemCalls = $systemCalls;
    }

    public function prepare()
    {
        try {
            $this->socketServer->listen();
        } catch (SocketServerException $e) {
            throw new WorkerException("An error occurred \"{$e->getMessage()}\"");
        }

        $this->systemCalls->setSignalHandler(
            SIGCHLD,
            function() {
                $this->socketServer->refreshConnections();
            });
    }

    public function run()
    {
        $this->socketServer->acceptConnections();
    }

    public function shutdown()
    {
        $this->socketServer->closeSocket($this->socketServer->getSocket());
        $this->socketServer->dropConnections();
    }

}