<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Console extends Admin
{
    public function show($input)
    {
        $this->response->breadcrumb->addItem(fx::alang('Console'), '#admin.console.show');
        $this->response->submenu->setMenu('console');
        $fields = array(
            'console_text' => array(
                'name'  => 'console_text',
                'type'  => 'text',
                'code'  => 'htmlmixed',
                'value' => isset($input['console_text']) ? $input['console_text'] : '<?php' . "\n"
            )
        );
        $fields [] = $this->ui->hidden('entity', 'console');
        $fields [] = $this->ui->hidden('action', 'execute');
        $this->response->addFormButton(array(
            'label'     => fx::alang('Execute') . ' (Ctrl+Enter)',
            'is_submit' => false,
            'class'     => 'execute'
        ));
        $this->response->addFields($fields);
        fx::page()->addJsFile(fx::path('@floxim') . '/Admin/js/console.js');
        return array('show_result' => 1);
    }
    
    protected static function preProcess($code)
    {
        $code = preg_replace("~^<\?(?:php)?~", '', $code);
        $lines = explode("\n", $code);
        foreach ($lines as &$line) {
            if (preg_match("~^\s*>~", $line)) {
                $line = preg_replace("~[\r]~", '', $line);
                $line = preg_replace("~^\s*>~", 'fx::debug(', $line);
                $line = preg_replace("~;\s*$~", '', $line);
                $line .= ');';
            }
        }
        $code = join("\n", $lines);
        return $code;
    }

    public function execute($input)
    {
        if ($input['console_text']) {
            ob_start();
            $code = $input['console_text'];
            
            fx::env('console', true);
            fx::env('css_bundle', 'none');
            fx::env('console_buffer', '');
            fx::config('dev.on', true);
            $code = self::preProcess($code);
            eval($code);
            $res = ob_get_clean();
            $buffer = fx::env('console_buffer');
            if ($buffer) {
                $res .= '<hr /><b>Buffered:</b>'.$buffer;
            }
            return array(
                'result' => $res
            );
        }
    }
}
