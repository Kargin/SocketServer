<?php

namespace HW3;

use HW3\Exceptions\Daemon\AlreadyRunningException;
use HW3\Exceptions\Daemon\AlreadyStoppedException;
use HW3\Exceptions\Daemon\DaemonException;
use HW3\Exceptions\Validator\ValidatorException;
use HW3\Exceptions\Daemon\ForkException;
use HW3\Exceptions\Worker\WorkerException;
use HW3\Interfaces\DaemonInterface;
use HW3\Interfaces\FileSystemInterface;
use HW3\Interfaces\LoggerInterface;
use HW3\Interfaces\SystemCallsInterface;
use HW3\Interfaces\ValidatorInterface;
use HW3\Interfaces\WorkerInterface;

class Daemon implements DaemonInterface
{
    protected $pidPath;
    protected $mode;
    protected $validator;
    protected $worker;
    protected $fileSystem;
    protected $systemCalls;
    protected $logger;
    protected $isRunning = false;
    protected $socket;

    public function __construct(
        ValidatorInterface $validator,
        LoggerInterface $logger,
        WorkerInterface $worker,
        string $pidPath = "/tmp/php_daemon.pid",
        FileSystemInterface $fileSystem = null,
        SystemCallsInterface $systemCalls = null
    )
    {
        $this->pidPath = $pidPath;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->worker = $worker;

        if ($fileSystem === null) {
            $fileSystem = new FileSystem;
        }

        if ($systemCalls === null) {
            $systemCalls = new SystemCalls;
        }

        $this->fileSystem = $fileSystem;
        $this->systemCalls = $systemCalls;
    }

    public function start(bool $isDaemon = true)
    {
        $pid = $this->getPid();

        if ($pid !== 0) {
            throw new AlreadyRunningException("Daemon is already running with pid {$pid}");
        }

        try {
            $this->validator->validatePath($this->pidPath);
        }
        catch (ValidatorException $e) {
            throw new DaemonException("An error occurred \"{$e->getMessage()}\"");
        }

        if ($isDaemon) {
            $pid = $this->systemCalls->fork();

            if ($pid === -1) {
                throw new ForkException("There was an error while forking");
            }
            // parent process
            if ($pid) {
                $this->fileSystem->putFileContents($this->pidPath, $pid);
                return true;
            }

            $this->fileSystem->closeDescriptors();
            $sid = $this->systemCalls->setSid();

            if ($sid === -1) {
                $this->systemCalls->exit(-1);
            }
        }

        $this->systemCalls->setSignalHandler(
            SIGTERM,
            function() {
                $this->logger->log("Received signal SIGTERM");
                $this->isRunning = false;
                $this->worker->shutdown();
            });

        $pid = $this->getPid();
        $this->logger->log("Daemon started with PID $pid");
        $this->isRunning = true;
        try {
            $this->worker->prepare();
        } catch (WorkerException $e) {
            throw new DaemonException("An error occurred \"{$e->getMessage()}\"");
        }

        while ($this->isRunning) {
            $this->worker->run();
            usleep(100);
            $this->systemCalls->sendSignals();
        }

        $this->logger->log("Daemon server successfully stopped");
        exit(0);
    }

    public function stop(): bool
    {
        $pid = $this->getPid();

        if ($pid === 0) {
            throw new AlreadyStoppedException('Daemon is already stopped');
        }

        $this->systemCalls->stopProcess($pid);
        $this->fileSystem->deleteFile($this->pidPath);

        return true;
    }

    public function restart()
    {
        $isRunning = $this->status();
        if ($isRunning) {
            $this->stop();
        }

        return $this->start();
    }

    public function status(): bool
    {
        $pid = $this->getPid();

        if ($pid === 0) {
            return false; // Daemon is not running
        }

        return true;

    }

    public function getPid(): int
    {
        if ($this->fileSystem->isFileExists($this->pidPath) === false) {
            return 0;
        }

        $pid = (int)$this->fileSystem->getFileContents($this->pidPath);

        if ($pid <= 0) {
            return 0;
        }

        return $pid;
    }
}