<?php

namespace HW3\Interfaces;

interface SystemCallsInterface
{
    public function fork();

    public function setSid();

    public function exit($exitCode);

    public function stopProcess($pid);

    public function sendSignals();

    public function setSignalHandler($signal, $signalHandler);

    public function waitAsync(&$status);

    public function waitSync(&$status);

    public function transferSignal($pid, $sig);
}