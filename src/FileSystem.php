<?php

namespace HW3;

use HW3\Interfaces\FileSystemInterface;

class FileSystem implements FileSystemInterface
{
    public function closeDescriptors()
    {
        if (fclose(STDIN) === false) {
            return false;
        }
        if (fclose(STDOUT) === false) {
            return false;
        }
        if (fclose(STDERR) === false) {
            return false;
        }
        return true;
    }

    public function isFileExists($filename): bool
    {
        return file_exists($filename);
    }

    public function getFileContents($filename)
    {
        return file_get_contents($filename);
    }

    public function isDirectory($dirname)
    {
        return is_dir($dirname);
    }

    public function isWritable($filename): bool
    {
        return is_writable($filename);
    }

    public function getDirectoryName($path): string
    {
        return dirname($path);
    }

    public function putFileContents($filename, $data, $flags=0)
    {
        return file_put_contents($filename, $data, $flags);
    }

    public function deleteFile($filename)
    {
        return unlink($filename);
    }
}