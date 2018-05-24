<?php

namespace HW3\Interfaces;

interface DaemonInterface
{
    public function start(bool $isDaemon = true);

    public function stop();

    public function restart();

    public function status();

    public function getPid();
}