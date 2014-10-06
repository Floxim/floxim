<?php

namespace Floxim\Floxim\Console\Command;

use Floxim\Floxim\System\Console;

class Module extends Console\Command {

    public function doNew($name,$components = '') {
        var_dump($name,$components);
    }
}