<?php

namespace HW3;

use HW3\Interfaces\SystemCallsInterface;

class SystemCalls implements SystemCallsInterface
{
    public function fork()
    {
        return pcntl_fork();
    }

    public function setSid(): int
    {
        return posix_setsid();
    }

    public function exit($exitCode)
    {
        return exit($exitCode);
    }

    public function stopProcess($pid)
    {
        return posix_kill($pid, SIGTERM);
    }

    public function sendSignals(): bool
    {
        return pcntl_signal_dispatch();
    }

    public function setSignalHandler($signal, $signalHandler)
    {
        return pcntl_signal($signal, $signalHandler);
    }

    public function waitAsync(&$status): int
    {
        return pcntl_waitpid(-1, $status, WNOHANG);
    }

    public function waitSync(&$status): int
    {
        return pcntl_waitpid(-1, $status);
    }

    public function transferSignal($pid, $sig): bool
    {
        return posix_kill($pid, $sig);
    }
}