<?php

namespace MarvinCaspar\Composer;

use MarvinCaspar\Composer\Command\PublishCommand;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [ new PublishCommand() ];
    }
}