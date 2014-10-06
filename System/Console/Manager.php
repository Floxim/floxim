<?php

namespace Floxim\Floxim\System\Console;

class Manager {

    protected $scriptName;
    protected $paths = array();
    protected $commands = array();

    public function run() {
        $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : array(__FILE__);
        $this->scriptName = $args[0];
        array_shift($args);
        // Define command
        if (isset($args[0])) {
            $name = $args[0];
            array_shift($args);
        } else {
            $name = 'help';
        }
        // Create command
        if (!($command = $this->createCommand($name))) {
            $command = $this->createCommand('help');
        }
        return $command->run($args);
    }

    public function addPath($path) {
        $this->paths[] = rtrim(realpath($path),'/') . '/';
        array_unique($this->paths);
    }

    public function addCommands($commands) {
        if (is_array($commands)) {
            foreach ($commands as $name => $params) {
                $this->addCommand($name,$params);
            }
        }
    }

    public function addCommand($name,$params) {
        $this->commands[$name] = $params;
    }

    public function getCommand($name) {
        return isset($this->commands[$name]) ? $this->commands[$name] : null;
    }

    public function getCommands() {
        return $this->commands;
    }

    public function getScriptName() {
        return $this->scriptName;
    }

    public function createCommand($name) {
        $name = strtolower($name);

        if ($command = $this->getCommand($name)) {
            return new $command($name,$this);
        } elseif ($name == 'help') {
            return new CommandHelp($name,$this);
        }
        return null;
    }
}