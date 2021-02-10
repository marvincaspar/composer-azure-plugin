<?php

namespace MarvinCaspar\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use MarvinCaspar\Composer\Command\PublishCommand;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [new PublishCommand()];
    }
}