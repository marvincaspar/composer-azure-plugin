<?php declare(strict_types=1);

use MarvinCaspar\Composer\Command\PublishCommand;
use MarvinCaspar\Composer\CommandProvider;
use PHPUnit\Framework\TestCase;

final class CommandProviderTest extends TestCase
{
    public function testCopyAndRemoveDirectory(): void
    {
        $commandProvider = new CommandProvider();
        $commands = $commandProvider->getCommands();

        $this->assertCount(1, $commands);
        $this->assertInstanceOf(
            PublishCommand::class,
            $commands[0]
        );
    }
}