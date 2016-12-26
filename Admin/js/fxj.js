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
    
    jQuery.fn.extend({
        onElem: function(event, elem_name, callback) {
            var block_class = jQuery.BEM.getBlockClass(this),
                elem_class = jQuery.BEM.buildElemClass(block_class, elem_name);
                
            this.on(
                event, 
                '.'+elem_class, 
                callback
            );
            return this;
        }
    });
    
    $fxj.valHooks.input = {
        get: function( elem ) {
            if (elem.getAttribute('data-json-val') !== 'true') {
                return undefined;
            }
            try {
                var res = JSON.parse(elem.value);
            } catch (e) {
                console.log(e, elem, elem.value);
            }
            return res;
        }
    };
    
    $fxj.valHooks.checkbox.get = function(elem) {
        if (elem.hasAttribute('data-fx-unchecked-value')) {
            return elem.checked ? elem.value : elem.getAttribute('data-fx-unchecked-value');
        }
        return undefined;
    };
    
    if (console && typeof console === 'object') {
        console.fix = function() {
            var res = [];
            for (var i = 0; i < arguments.length; i++) {
                var arg = arguments[i];
                if (typeof arg === 'object') {
                    arg = $.extend(true, {}, arg);
                }
                res.push(arg);
            }
            return console.log.apply(console, res);
        };
    }
    
    window.fix = function(data) {
        return JSON.parse(JSON.stringify(data));
    };
}