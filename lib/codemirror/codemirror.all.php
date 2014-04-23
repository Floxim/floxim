<?
header("Content-type: text/javascript; charset=utf-8");
$path = realpath(dirname(__FILE__));
$files = array(
	'lib/codemirror.js',
	'mode/xml/xml.js',
	'mode/htmlmixed/htmlmixed.js',
	'mode/css/css.js',
	'mode/javascript/javascript.js'
);
$ds = DIRECTORY_SEPARATOR;
foreach ($files as $file) {
	$file = $path.$ds.str_replace("/", $ds, $file);
	echo file_get_contents($file)."\n\n";
}
?>