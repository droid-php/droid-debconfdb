<?php

namespace Droid\Plugin\Debconfdb;

class DroidPlugin
{
    public function __construct($droid)
    {
        $this->droid = $droid;
    }

    public function getCommands()
    {
        return array(
        );
    }
}
