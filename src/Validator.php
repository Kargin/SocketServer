<?php

namespace HW3;

use HW3\Exceptions\Validator\InvalidPathException;
use HW3\Interfaces\FileSystemInterface;
use HW3\Interfaces\ValidatorInterface;

class Validator implements ValidatorInterface
{
    protected $fileSystem;

    public function __construct(FileSystemInterface $fileSystem = null)
    {
        if ($fileSystem === null) {
            $fileSystem = new FileSystem;
        }

        $this->fileSystem = $fileSystem;
    }

    public function validatePath($path)
    {
        if ($this->fileSystem->isDirectory($path)) {
            throw new InvalidPathException("Path \"{$path}\" must be a file, not a directory");
        }

        $dir = dirname($path);

        if (empty($dir)) {
            throw new InvalidPathException("Invalid directory \"{$path}\"");
        }

        if (!$this->fileSystem->isWritable($dir)) {
            throw new InvalidPathException("Directory \"{$dir}\" is not writable for this user");
        }
    }

    public function validateConfigParams($configParameters, $configAllowedKeys)
    {
        $hasErrors = false;
        $unknownKeys = [];

        foreach ($configParameters as $key => $value) {
            if (!in_array($key, $configAllowedKeys)) {
                $unknownKeys[] = $key;
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            echo sprintf('[ERROR] Following keys in configuration file are unknown: %s',
                array_walk(
                    $unknownKeys,
                    function ($item) {
                        echo "$item ";
                    })
            );
            throw new InvalidConfigParameterException("Following keys in configuration file are unknown: $unknownKeys");
        }
    }

    public function validatePort($port)
    {
        if (!preg_match('/^\d+$/', $port)) {
            throw new portFormatException("Invalid port number format");
        }

        if ($port <= 1024 && $port > 65535) {
            throw new portOutOfBoundException("port number must be > 1024 and < 65536");
        }
    }
}