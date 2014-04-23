(function($)
{
    $.fn.generate_selector = function(parent) {
        if (this.length == 0) {
            return false;
        }
        if (typeof(parent) == 'undefined') {
            parent = document;
        } else if (parent instanceof $) {
            parent = parent.get(0);
        }
        var selector = [];
        var node = this.first();
        while (node.length > 0 && node.get(0) !== parent) {
            selector.unshift(':nth-child(' + (node.index() + 1)+ ')');
            node = node.parent();
        }
        return '>'+selector.join('>'); 
    };
    $.fn.descendant_or_self = function(selector) {
        return this.find(selector).add( this.filter(selector));
    };
    $.fn.putCursorAtEnd = function() {
        return this.each(function() {
            $(this).focus()
            // If this function exists...
            if (this.setSelectionRange) {
                // ... then use it
                // (Doesn't work in IE)

                // Double the length because Opera is inconsistent about whether a carriage return is one character or two. Sigh.
                var len = $(this).val().length * 2;
                this.setSelectionRange(len, len);
            } else {
                // ... otherwise replace the contents with itself
                // (Doesn't work in Google Chrome)
                $(this).val($(this).val());
            }
            // Scroll to the bottom, in case we're in a tall textarea
            // (Necessary for Firefox and Google Chrome)
            this.scrollTop = 999999;
        });
    };
})($fxj);

function fx_call_user_func (fn, options) {
    var oFunction = new Function("options" , "return "+fn+"(options);");
    return oFunction(options);  
}

function fx_object_length ( obj ) {
	if (obj instanceof Array) {
		return obj.length;
	}
    var count = 0; 
    for(var k in obj)  { 
        count++;
    } 
    return count;
}

function fx_object_get_first_key ( obj ) {
    var key; 
    for(var k in obj)  { 
        key = k;
        break;
    } 
    return key
}

Array.prototype.unique = function( b ) {
    var a = [], i, l = this.length;
    for( i=0; i<l; i++ ) {
        if( a.indexOf( this[i], 0, b ) < 0 ) {
            a.push( this[i] );
        }
    }
    return a;
};

Array.prototype.contains = function( elem ) {
    for ( var i = 0, length = this.length; i < length; i++ ) {
        if ( this[i] == elem ) {
            return true;
        }
    }

    return false;
};

function array_diff (arr1) {
    // http://kevin.vanzonneveld.net
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Sanjoy Roy
    // +    revised by: Brett Zamir (http://brett-zamir.me)
    // *     example 1: array_diff(['Kevin', 'van', 'Zonneveld'], ['van', 'Zonneveld']);
    // *     returns 1: {0:'Kevin'}
    var retArr = [],
        argl = arguments.length,
        k1 = '',
        i = 1,
        k = '',
        arr = {};

    arr1keys: for (k1 in arr1) {
        for (i = 1; i < argl; i++) {
            arr = arguments[i];
            for (k in arr) {
                if (arr[k] === arr1[k1]) {
                    // If it reaches here, it was found in at least one array, so try next value
                    continue arr1keys;
                }
            }

            retArr.push(arr1[k1]);
        }
    }

    return retArr;
}

function array_intersect (arr1) {
    // http://kevin.vanzonneveld.net
    // +   original by: Brett Zamir (http://brett-zamir.me)
    // %        note 1: These only output associative arrays (would need to be
    // %        note 1: all numeric and counting from zero to be numeric)
    // *     example 1: $array1 = {'a' : 'green', 0:'red', 1: 'blue'};
    // *     example 1: $array2 = {'b' : 'green', 0:'yellow', 1:'red'};
    // *     example 1: $array3 = ['green', 'red'];
    // *     example 1: $result = array_intersect($array1, $array2, $array3);
    // *     returns 1: {0: 'red', a: 'green'}
    var retArr = [],
        argl = arguments.length,
        arglm1 = argl - 1,
        k1 = '',
        arr = {},
        i = 0,
        k = '';

    arr1keys: for (k1 in arr1) {
        arrs: for (i = 1; i < argl; i++) {
            arr = arguments[i];
            for (k in arr) {
                if (arr[k] === arr1[k1]) {
                    if (i === arglm1) {
                        retArr.push(arr1[k1]);
                    }
                    // If the innermost loop always leads at least once to an equal value, continue the loop until done
                    continue arrs;
                }
            }
            // If it reaches here, it wasn't found in at least one array, so try next value
            continue arr1keys;
        }
    }

    return retArr;
}