/**
 * Here is Imperavi Redactor plugin to integrate it with CodeMirror
 */
(function ($) {
if (!window.RedactorPlugins) {
    window.RedactorPlugins = {};
}

window.RedactorPlugins.codekit = {
    init: function() {
        var _redactor = this;
        this.buttonAdd('codekit', 'Embed Code', $.proxy(function() {
            var $node = $(
                '<pre class="fx_codekit fx_internal_block" contenteditable="false"></pre>'
            );
            this.insertNode($node);
            this.createCodemirror($node, true);
        }, this));
        
        $('.fx_codekit', this.getEditor()).each(function () {
            _redactor.createCodemirror($(this), false);
        });
    },
    createCodemirror: function($node, set_focus) {
        var _redactor = this;
        // do not let keydown event bubble up to redactor
        $node.on('keydown', function(e) {
            e.stopPropagation();
            _redactor.sync();
            return;
        });
        
        _redactor.getEditor().off('.codekit_keyup').on('keyup.codekit_keyup', function (e){ 
            var $c_block = $(_redactor.getBlock());
            $('.fx_codekit_spacer', _redactor.getEditor()).each(function() {
                var $sp = $(this);
                if ($.trim($sp.text())) {
                    $sp.removeClass('fx_codekit_spacer');
                }
                $sp.removeClass('fx_codekit_spacer_focused');
            });
            if ($c_block.hasClass('fx_codekit_spacer')) {
                $c_block.addClass('fx_codekit_spacer_focused');
            }
        });
        
        var make_spacer = function($node) {
            if (!$node) {
                $node = $('<p></p>');
            }
            $node.addClass('fx_codekit_spacer').html('&nbsp;');
            return $node;
        };
        
        $.each([ 
                [$node[0].previousSibling, 'before'], 
                [$node[0].nextSibling, 'after'] 
            ], function(index, item) {
            var c_node = item[0];
            if (!c_node) {
                item[1] === 'before' ? $node.before(make_spacer()) : $node.after(make_spacer());
            } else if (c_node.nodeName === 'P' && c_node.childNodes.length === 0) {
                make_spacer($(c_node));
            }
        });
        
        var source = $node.text();
        $node.html('');
        var $textarea = $('<textarea></textarea>');
        $textarea.val(source);
        $node.append($textarea);
        if (set_focus) {
            $textarea.focus();
        }
        
        var code_type = 'php';

        var config = {
            mode:code_type,
            lineNumbers: false,
            matchBrackets: true,
            tabMode: "indent",
            indentWithTabs:false,
            indentUnit:4,
            electricChars: false,
            smartIndent: false,
            autofocus:set_focus ? true : false,
            extraKeys: { 
                Tab: function (cm) {
                    if (cm.somethingSelected()) {
                        cm.indentSelection("add");
                    } else {
                        cm.replaceSelection(cm.getOption("indentWithTabs")? "\t":
                        Array(cm.getOption("indentUnit") + 1).join(" "), "end", "+input");
                    }
                }
            }
        };
        
        var cCodeMirror = CodeMirror.fromTextArea($textarea[0], config);
        $node.on('fx_stop_editing', function() {
            cCodeMirror.save();
            var source = $textarea.val();
            source = _redactor.cmEscapeHtml(source);
            $node.before('<pre class="fx_codekit fx_internal_block" contenteditable="false">'+source+'</pre>');
            $node.remove();
            $('.fx_codekit_spacer', _redactor.getEditor()).each(function() {
                var $sp = $(this);
                if (!$.trim($sp.text())) {
                    $sp.remove();
                } else {
                    $sp.removeClass('fx_codekit_spacer').removeClass('fx_codekit_spacer_focused');
                }
            });
            _redactor.sync();
        });
        cCodeMirror.refresh();
    },
    cmEscapeHtml: function(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
};

})(window.$fxj);