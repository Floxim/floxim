/** 
 * jQuery alias for using inside floxim javascript code
 * This script is loaded right after system jQuery
 * All the system jQuery-powered code should be wrapped like this:
 (function($) {
    // use $ as usual, e.g.:
    $.ready(...)
})($fxj);
*/
if (jQuery) {
    $fxj = jQuery;
}