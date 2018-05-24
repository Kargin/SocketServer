<?php

namespace HW3;

use HW3\Interfaces\ConnectionHandlerInterface;
use HW3\Interfaces\LoggerInterface;
use HW3\Interfaces\FileSystemInterface;
use HW3\Interfaces\SystemCallsInterface;
use Kargin\Bracketeer;

class BracketeerHandler implements ConnectionHandlerInterface
{
    protected $pid;
    protected $logger;
    protected $systemCalls;
    protected $fileSystem;
    protected $connection;
    protected $isRunning = false;

    public function __construct(
        LoggerInterface $logger,
        FileSystemInterface $fileSystem = null,
        SystemCallsInterface $systemCalls = null
    )
    {
        $this->logger = $logger;

        if ($fileSystem === null) {
            $fileSystem = new FileSystem;
        }

        if ($systemCalls === null) {
            $systemCalls = new SystemCalls;
        }

        $this->fileSystem = $fileSystem;
        $this->systemCalls = $systemCalls;
    }

    public function handle($connection)
    {
        $this->pid = getmypid();
        $this->connection = $connection;
        $bracketeer = new Bracketeer();
        $this->logger->log("[$this->pid] client connected");
        $msg = "\nWelcome to the Bracketeer composer library server.\n" .
            "Type any number of brackets to test if they are balanced and press ENTER.\n" .
            "To quit, type 'quit'.\n";
        socket_write($this->connection, $msg, strlen($msg));
        $this->isRunning = true;
        $this->systemCalls->setSignalHandler(
            SIGTERM,
            function() {
                $this->logger->log("[$this->pid] Received signal SIGTERM");
                $this->isRunning = false;
            });

        while ($this->isRunning) {
            $isBalanced = false;
            $hasException = false;
            $output = "False. Entered string is NOT balanced.\n";
            $buf = socket_read($this->connection, 2048, PHP_BINARY_READ);

            if (empty($buf)) {
                $this->systemCalls->sendSignals();
                usleep(100);
            } elseif ($buf === false) {
                $this->logger->error("[$this->pid] socket_read() failed. Reason: " . socket_strerror(socket_last_error($this->connection)));
                break;
            } else {
                if (!$buf = trim($buf)) {
                    continue;
                }

                if ($buf == 'quit') {
                    break;
                }

                $this->logger->log("[$this->pid] Client entered: " . $buf);

                try {
                    $isBalanced = $bracketeer->isBalanced($buf);
                } catch (\InvalidArgumentException $e) {
                    $hasException = true;
                    $output = sprintf("ERROR: %s%s", $e->getMessage(), PHP_EOL);
                }

                if (!$hasException && $isBalanced) {
                    $output = "True. Entered string is balanced!\n";
                }

                if (socket_write($this->connection, $output, strlen($output)) === false) {
                    $this->logger->error("[$this->pid] socket_write() failed. Reason: " . socket_strerror(socket_last_error($this->connection)));
                    break;
                }

                $this->logger->log("[$this->pid] answers: $output");
            }
        }
        $this->logger->log("[$this->pid] client disconnected");
        exit();
    }
}