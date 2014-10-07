<?php

namespace Floxim\Floxim\System\Console;

class CommandHelp extends Command {

    public function run($args) {
        $manager = $this->getManager();
        $commands = $manager->getCommands();

        if (isset($args[0])) {
            $name = strtolower($args[0]);
        }

        if (isset($name) and isset($commands[$name])) {
            echo $manager->createCommand($name)->getHelp();
        } elseif ($commands) {
            echo "Floxim command manager\n";
            echo "Usage: " . $manager->getScriptName() . " <command-name> [parameters...]\n";
            echo "\nThe following commands are available:\n";
            $commandNames = array_keys($commands);
            sort($commandNames);
            echo ' - ' . implode("\n - ", $commandNames);
            echo "\n\nTo see individual command help, use the following:\n";
            echo " " . $manager->getScriptName() . " help <command-name>\n";
        } else {
            echo "No available commands.\n";
        }
        return 1;
    }
}