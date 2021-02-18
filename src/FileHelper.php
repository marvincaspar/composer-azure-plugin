<?php

namespace MarvinCaspar\Composer;

class FileHelper
{
    /**
     * Recursively delete a directory
     */
    public function removeDirectory(string $root_path)
    {
        $dir = opendir($root_path);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $root_path . '/' . $file;

                if (is_dir($full)) {
                    self::removeDirectory($full);
                } else {
                    unlink($full);
                }
            }
        }

        closedir($dir);
        rmdir($root_path);
    }

    /**
     * Recursively copy a directory
     */
    public function copyDirectory(string $src, string $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::copyDirectory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }

        closedir($dir);
    }
}