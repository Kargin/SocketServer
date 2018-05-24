<?php

namespace HW3\Interfaces;

interface ValidatorInterface
{
    public function validatePath($path);

    public function validateConfigParams($configParameters, $configAllowedKeys);

    public function validatePort($port);
}