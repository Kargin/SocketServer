<?php

namespace HW3\Interfaces;

interface LoggerInterface
{
    public function log($message);

    public function info($message);

    public function error($message);
}