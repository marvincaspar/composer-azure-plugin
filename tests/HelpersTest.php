<?php declare(strict_types=1);

use MarvinCaspar\Composer\Helpers;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testCopyAndRemoveDirectory(): void
    {
        $dir = '../tmp';
        Helpers::copyDirectory('.', $dir);
        $this->assertTrue(is_dir($dir));
        $this->assertTrue(is_dir($dir . '/src'));
        Helpers::removeDirectory($dir);
        $this->assertFalse(is_dir($dir));
    }
}