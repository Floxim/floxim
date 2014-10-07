<?php

namespace Floxim\Floxim\System\Console;

class Command {

    protected $defaultAction = 'index';

    private $name;
    private $manager;

    public function __construct($name, $manager) {
        $this->name = $name;
        $this->manager = $manager;
    }

    public function init() {

    }

    public function run($args) {
        list($action, $options, $args) = $this->resolveRequest($args);
        $methodName = 'do' . $action;
        if (!preg_match('/^\w+$/', $action) || !method_exists($this, $methodName)) {
            $this->usageError("Unknown action: " . $action);
        }
        $method = new \ReflectionMethod($this, $methodName);
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
            $this->usageError("Unknown options: " . implode(', ', array_keys($options)));
        }
        return $method->invokeArgs($this, $params);
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
            if (strpos($name, 'do') === 0) {
                $name = substr($name, 2);
                $name[0] = strtolower($name[0]);
                $help = $name;
                foreach ($method->getParameters() as $param) {
                    $optional = $param->isDefaultValueAvailable();
                    $defaultValue = $optional ? $param->getDefaultValue() : null;
                    if (is_array($defaultValue)) {
                        $defaultValue = str_replace(array("\r\n", "\n", "\r"), "", print_r($defaultValue, true));
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
            if (preg_match('/^--(\w+)(=(.*))?$/', $arg, $matches)) {
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
        return array($action, $options, $params);
    }

    public function usageError($message) {
        echo "Error: $message\n\n" . $this->getHelp() . "\n";
        exit(1);
    }

    public function buildFileList($source_dir, $target_dir, $base_dir = '') {
        $list = array();
        $handle = opendir($source_dir);
        while (($file = readdir($handle)) !== false) {
            if (in_array($file, array('.', '..', '.svn', '.gitignore'))) {
                continue;
            }

            $source_path = $source_dir . DIRECTORY_SEPARATOR . $file;
            $target_path = $target_dir . DIRECTORY_SEPARATOR . $file;

            $name = ($base_dir === '') ? $file : $base_dir . '/' . $file;
            $list[$name] = array(
                'source' => $source_path, 'target' => $target_path
            );
            if (is_dir($source_path)) {
                $list = array_merge($list, $this->buildFileList($source_path, $target_path, $name));
            }
        }
        closedir($handle);
        return $list;
    }

    public function copyFiles($fileList) {
        $overwriteAll = false;
        foreach ($fileList as $name => $file) {
            $source = strtr($file['source'], '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
            $target = strtr($file['target'], '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
            $callback = isset($file['callback']) ? $file['callback'] : null;
            $callbackContent = isset($file['callback_content']) ? $file['callback_content'] : null;
            $params = isset($file['params']) ? $file['params'] : null;
            if (is_dir($source)) {
                $this->ensureDirectory($target);
                continue;
            }
            if ($callback !== null) {
                $content = call_user_func($callback, $source, $params);
            } else {
                $content = file_get_contents($source);
            }
            if ($callbackContent !== null) {
                $content = call_user_func($callbackContent, $content);
            }
            if (is_file($target)) {
                if ($content === file_get_contents($target)) {
                    echo " unchanged $name\n";
                    continue;
                }
                if ($overwriteAll) {
                    echo " overwrite $name\n";
                } else {
                    echo " exist $name\n";
                    echo " ...overwrite? [Yes|No|All|Quit] ";
                    $answer = trim(fgets(STDIN));
                    if (!strncasecmp($answer, 'q', 1)) {
                        return;
                    } elseif (!strncasecmp($answer, 'y', 1)) {
                        echo " overwrite $name\n";
                    } elseif (!strncasecmp($answer, 'a', 1)) {
                        echo " overwrite $name\n";
                        $overwriteAll = true;
                    } else {
                        echo " skip $name\n";
                        continue;
                    }
                }
            } else {
                $this->ensureDirectory(dirname($target));
                echo " generate $name\n";
            }
            file_put_contents($target, $content);
        }
    }

    public function ensureDirectory($directory) {
        if (!is_dir($directory)) {
            $this->ensureDirectory(dirname($directory));
            echo " mkdir " . strtr($directory, '\\', '/') . "\n";
            mkdir($directory);
        }
    }
}