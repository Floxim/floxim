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
        var button_action = $.proxy(function() {
            var selection = this.getSelectionHtml();
            var c_parent = this.getParent();
            
            if (c_parent) {
                var c_text = this.getCurrent();
                var offset = this.getCaretOffset(c_parent)
                if (c_parent.nodeName === 'CODE') {
                    if (selection || offset < c_text.length) {
                        this.inlineRemoveFormatReplace(c_parent);
                    } else {
                        var temp_node = $('<span>\u200B</span>');
                        $(c_parent).after(temp_node);
                        this.setCaret(temp_node, 1);
                    }
                    return;
                }
            }
            
            if (selection) {
                this.inlineFormat('code');
                return;
            }
            var $node = $('<pre class="fx_codekit fx_internal_block"></pre>');
            this.insertNode($node);
            this.createCodemirror($node, true);
        }, this);
        
        this.buttonAdd('codekit', 'Embed Code', button_action);
        
        $('.fx_codekit', this.getEditor()).each(function () {
            _redactor.createCodemirror($(this), false);
        });
        this.getEditor().on('keyup', function(e) {
            if (e.which === 186 && e.ctrlKey) {
                button_action();
                return false;
            }
        }).one('fx_stop_editing', function() {
            _redactor.cmStopEditing();
        });
        
    },
    createCodemirror: function($node, set_focus) {
        $node.attr('contenteditable', 'false');
        var _redactor = this;
        // do not let keydown event bubble up to redactor
        $node.on('keydown paste', function(e) {
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
        $node.data('codeMirror', cCodeMirror);
        cCodeMirror.refresh();
    },
    cmEscapeHtml: function(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },
    cmStopEditing:function() {
        var _redactor = this;
        var editor_node = _redactor.getEditor();
        $('.fx_codekit', editor_node).each(function() {
            var $node = $(this),
                cCodeMirror = $node.data('codeMirror');

            cCodeMirror.save();
            var source = $('textarea',$node).val();
            source = _redactor.cmEscapeHtml(source);
            $node.before('<pre class="fx_codekit fx_internal_block">'+source+'</pre>');
            $node.remove();
        });
        $('.fx_codekit_spacer', editor_node).each(function() {
            var $sp = $(this);
            if (!$.trim($sp.text())) {
                $sp.remove();
            } else {
                $sp.removeClass('fx_codekit_spacer').removeClass('fx_codekit_spacer_focused');
            }
        });
        _redactor.sync();
    }
};

})(window.$fxj);