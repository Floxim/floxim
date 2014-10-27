<?php
//todo: psr0 need fix - move code to exists controller or create new
require_once '../../../../../boot.php';

$ctr = new \Floxim\Floxim\Admin\Controller\File($_FILES + array('do_return' => true), 'upload_save');

$res = $ctr->process();
$path = $res['path'];
if (!$path) {
    die("OOPS");
}
echo stripslashes(json_encode(array('filelink' => $path)));