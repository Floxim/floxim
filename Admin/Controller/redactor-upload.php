<?php
require_once '../../../boot.php';

use Floxim\Floxim\System\Fx as fx;

$ctr = fx::controller('admin_file:upload_save', $_FILES + array('do_return' => true));
$res = $ctr->process();
$path = $res['path'];
if (!$path) {
    die("OOPS");
}
echo stripslashes(json_encode(array('filelink' => $path)));