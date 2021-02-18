<?php

namespace MarvinCaspar\Composer;

class FileHelper
{
    /**
     * Recursively delete a directory
     */
    public function removeDirectory(string $rootPath): void
    {
        $dir = opendir($rootPath);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $rootPath . DIRECTORY_SEPARATOR . $file;

                if (is_dir($full)) {
                    self::removeDirectory($full);
                } else {
                    unlink($full);
                }
            }
        }

        closedir($dir);
        rmdir($rootPath);
    }

    public function removeFile(string $filePath): void
    {
        unlink($filePath);
    }

    /**
     * Recursively copy a directory
     */
    public function copyDirectory(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                    self::copyDirectory($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                } else {
                    copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                }
            }
        }

        closedir($dir);
    }
}