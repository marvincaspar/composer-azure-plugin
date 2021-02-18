<?php declare(strict_types=1);

use MarvinCaspar\Composer\FileHelper;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testCopyAndRemoveDirectory(): void
    {
        $helper = new FileHelper();
        $dir = '../tmp';
        $helper->copyDirectory('.', $dir);
        $this->assertTrue(is_dir($dir));
        $this->assertTrue(is_dir($dir . '/src'));
        $helper->removeDirectory($dir);
        $this->assertFalse(is_dir($dir));
    }
}