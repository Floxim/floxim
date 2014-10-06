<?php

namespace Floxim\Floxim\System\Console;

class Command {

    protected $defaultAction = 'index';

    private $name;
    private $manager;

    public function __construct($name,$manager) {
        $this->name = $name;
        $this->manager = $manager;
    }

    public function init() {

    }

    public function run($args) {
        list($action,$options,$args) = $this->resolveRequest($args);
        $methodName = 'do' . $action;
        if (!preg_match('/^\w+$/',$action) || !method_exists($this,$methodName)) {
            $this->usageError("Unknown action: " . $action);
        }
        $method = new \ReflectionMethod($this,$methodName);
        $params = array();
        foreach ($method->getParameters() as $i => $param) {
            $name = $param->getName();
            if (isset($options[$name])) {
                if ($param->isArray()) {
                    $params[] = is_array($options[$name]) ? $options[$name] : array($options[$name]);
                } elseif (!is_array($options[$name])) {
                    $params[] = $options[$name];
                } else {
                    $this->usageError("Option --$name requires a scalar. Array is given.");
                }
            } elseif ($name === 'args') {
                $params[] = $args;
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                $this->usageError("Missing required option --$name.");
            }
            unset($options[$name]);
        }
        if (!empty($options)) {
            $this->usageError("Unknown options: " . implode(', ',array_keys($options)));
        }
        return $method->invokeArgs($this,$params);
    }

    public function getName() {
        return $this->name;
    }

    public function getManager() {
        return $this->manager;
    }

    public function getHelp() {
        $help = 'Usage: ' . $this->getManager()->getScriptName() . ' ' . $this->getName();
        $options = $this->getOptionHelp();
        if (empty($options)) {
            return $help . "\n";
        }
        if (count($options) === 1) {
            return $help . ' ' . $options[0] . "\n";
        }
        $help .= " <action>\nActions:\n";
        foreach ($options as $option) {
            $help .= ' ' . $option . "\n";
        }
        return $help;
    }

    public function getOptionHelp() {
        $options = array();
        $class = new \ReflectionClass(get_class($this));
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (strpos($name,'do') === 0) {
                $name = substr($name,2);
                $name[0] = strtolower($name[0]);
                $help = $name;
                foreach ($method->getParameters() as $param) {
                    $optional = $param->isDefaultValueAvailable();
                    $defaultValue = $optional ? $param->getDefaultValue() : null;
                    if (is_array($defaultValue)) {
                        $defaultValue = str_replace(array("\r\n","\n","\r"),"",print_r($defaultValue,true));
                    }
                    $name = $param->getName();
                    if ($name === 'args') {
                        continue;
                    }
                    if ($optional) {
                        $help .= " [--$name=$defaultValue]";
                    } else {
                        $help .= " --$name=value";
                    }
                }
                $options[] = $help;
            }
        }
        return $options;
    }

    protected function resolveRequest($args) {
        $options = array(); // named parameters
        $params = array(); // unnamed parameters
        foreach ($args as $arg) {
            if (preg_match('/^--(\w+)(=(.*))?$/',$arg,$matches)) {
                $name = $matches[1];
                $value = isset($matches[3]) ? $matches[3] : true;
                if (isset($options[$name])) {
                    if (!is_array($options[$name])) {
                        $options[$name] = array($options[$name]);
                    }
                    $options[$name][] = $value;
                } else {
                    $options[$name] = $value;
                }
            } elseif (isset($action)) {
                $params[] = $arg;
            } else {
                $action = $arg;
            }
        }
        if (!isset($action)) {
            $action = $this->defaultAction;
        }
        return array($action,$options,$params);
    }

    public function usageError($message) {
        echo "Error: $message\n\n" . $this->getHelp() . "\n";
        exit(1);
    }
}