<?php

namespace Floxim\Floxim\System\Console;

class Manager
{

    protected $scriptName;
    protected $paths = array();
    protected $commands = array();

    public function run($args = null)
    {
        if (!$args) {
            $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : array(__FILE__);
        } elseif (is_scalar($args)) {
            $arg_string = $args;
            $args = array(\Floxim\Floxim\System\Fx::path()->fileName(__FILE__));

            foreach (self::parseArgs($arg_string) as $arg) {
                $args [] = $arg;
            }
        }
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

    protected static function parseArgs($s)
    {
        $parts = preg_split('~([\"\\\'])(.+?)(\1)~', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        $res = array();
        $c_quot = null;
        $quots = array('"', "'");
        foreach ($parts as $p) {
            if ($c_quot === null && in_array($p, $quots)) {
                $c_quot = $p;
                continue;
            }
            if ($c_quot && $p === $c_quot) {
                $c_quot = null;
                continue;
            }
            if ($c_quot) {
                $res [] = $p;
                continue;
            }
            $sub_parts = explode(" ", $p);
            foreach ($sub_parts as $sp) {
                $sp = trim($sp);
                if (!empty($sp)) {
                    $res [] = trim($sp);
                }
            }
        }
        return $res;
    }

    public function addPath($path)
    {
        $this->paths[] = rtrim(realpath($path), '/') . '/';
        array_unique($this->paths);
    }

    public function addCommands($commands)
    {
        if (is_array($commands)) {
            foreach ($commands as $name => $params) {
                $this->addCommand($name, $params);
            }
        }
    }

    public function addCommand($name, $params)
    {
        $this->commands[$name] = $params;
    }

    public function getCommand($name)
    {
        return isset($this->commands[$name]) ? $this->commands[$name] : null;
    }

    public function getCommands()
    {
        return $this->commands;
    }

    public function getScriptName()
    {
        return $this->scriptName;
    }

    public function createCommand($name)
    {
        $name = strtolower($name);

        if ($command = $this->getCommand($name)) {
            return new $command($name, $this);
        } elseif ($name == 'help') {
            return new CommandHelp($name, $this);
        }
        return null;
    }
}