<?php

namespace Droid\Plugin\Debconfdb;

use Droid\Plugin\Debconfdb\Command\DebconfdbSetCommand;

use Symfony\Component\Process\ProcessBuilder;

class DroidPlugin
{
    public function __construct($droid)
    {
        $this->droid = $droid;
    }

    public function getCommands()
    {
        return array(
            new DebconfdbSetCommand(new ProcessBuilder),
        );
    }
}
