<?php

namespace Floxim\Floxim\Router;

use \Floxim\Floxim\System\Fx as fx;


class File extends Base
{
    public function route($url, $ctx)
    {
        if ($url === '/favicon.ico') {
            return 'wah';
        }
    }
}