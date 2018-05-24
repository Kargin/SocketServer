<?php

namespace HW3\Interfaces;

interface FileSystemInterface
{
    public function closeDescriptors();

    public function getFileContents($filename);

    public function isFileExists($filename);

    public function isDirectory($dirname);

    public function isWritable($filename);

    public function getDirectoryName($path);

    public function putFileContents($filename, $data, $flags=0);

    public function deleteFile($filename);
}