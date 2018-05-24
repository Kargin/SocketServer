<?php

namespace HW3;

use HW3\Exceptions\Logger\LoggerException;
use HW3\Exceptions\Validator\ValidatorException;
use HW3\Interfaces\FileSystemInterface;
use HW3\Interfaces\LoggerInterface;
use HW3\Interfaces\ValidatorInterface;

class Logger implements LoggerInterface
{
    protected $logFile;
    protected $fileSystem;
    protected $validator;
    protected $logFormat;
    protected $logPrefix;

    public function __construct(
        ValidatorInterface $validator,
        $logPrefix = '',
        $logFile = '/tmp/logger.log',
        $logFormat = 'Y.m.d H:i:s',
        FileSystemInterface $fileSystem = null
    )
    {
        if ($fileSystem === null) {
            $fileSystem = new FileSystem;
        }

        $this->logPrefix = $logPrefix;
        $this->logFile = $logFile;
        $this->logFormat = $logFormat;
        $this->fileSystem = $fileSystem;
        $this->validator = $validator;

        try {
            $this->validator->validatePath($this->logFile);
        }
        catch (ValidatorException $e) {
            throw new LoggerException("An error occurred \"{$e->getMessage()}\"");
        }
    }

    public function log($message)
    {
        $formattedMessage = sprintf('%s %s %s', date($this->logFormat), $this->logPrefix, $message . PHP_EOL);
        $this->fileSystem->putFileContents($this->logFile, $formattedMessage, FILE_APPEND);
    }

    public function info($message)
    {
        $formattedMessage = sprintf('%s %s', '[INFO] ', $message);
        $this->log($formattedMessage);
    }

    public function error($message)
    {
        $formattedMessage = sprintf('%s %s', '[ERROR] ', $message);
        $this->log($formattedMessage);
    }
}