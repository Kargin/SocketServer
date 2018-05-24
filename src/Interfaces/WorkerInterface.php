<?php

namespace HW3\Interfaces;

interface WorkerInterface
{
    public function prepare();

    public function run();

    public function shutdown();
}