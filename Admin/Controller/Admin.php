<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System;
use Floxim\Floxim\Admin as FxAdmin;
use Floxim\Floxim\System\Fx as fx;

class Admin extends System\Controller
{

    protected $action = 'adminOffice';

    protected $process_do_return = false;

    protected $entity_type;
    protected $save_history = true;

    /**
     *
     * @var type \Floxim\Floxim\Admin\Response
     */
    protected $response;

    protected $ui;

    public function __construct($input = array(), $action = null, $do_return = false)
    {
        parent::__construct($input, $action);
        $this->entity_type = strtolower(fx::getClassNameFromNamespaceFull(get_class($this)));
        $this->ui = new FxAdmin\Ui;

        $this->process_do_return = isset($input['do_return']) ? $input['do_return'] : $do_return;
    }

    public function getHiddenFields($fields = array())
    {
        $input = $this->input;
        $fields = array_merge($fields, array('entity', 'action'));
        $res = array();
        foreach ($fields as $f) {
            $res []= $this->ui->hidden($f, isset($input[$f]) ? $input[$f] : '');
        }
        $res []= $this->ui->hidden('sent', 1);
        return $res;
    }

    public function isSent()
    {
        return isset($this->input['sent']) && $this->input['sent'];
    }

    public function process()
    {

        $input = $this->input;
        $action = $this->action;

        if (!fx::isAdmin()) {
            $result = $this->adminOffice($input);
            if (is_string($result)) {
                return $result;
            }
        }

        if (!$action || !is_callable(array($this, $action))) {
            die("Error! Class:" . get_class($this) . ", action:" . htmlspecialchars($action));
        }

        $this->response = new FxAdmin\Response($input);
        $result = $this->$action($input);
        if (is_string($result)) {
            return $result;
        }

        if (isset($input['posting']) && $input['posting']) {
            if (!isset($result['text']) || !$result['text']) {
                $result['text'] = $this->getStatusText();
            }
        }
        
        if ($this->response) {
            $result = array_merge($result ? $result : array(), $this->response->toArray());
        }
        // force numeric indexes for fields to preserve order
        if (isset($result['fields']) && is_array($result['fields'])) {
            $result['fields'] = array_values($result['fields']);
        }
        if ($this->process_do_return) {
            return $result;
        }
        return json_encode($result);
    }

    protected function getStatusText()
    {
        return fx::alang('Saved', 'system');
    }

    protected function getActiveTab()
    {
        return $this->input['params'][1];
    }

    public function getVendorField()
    {
        $field = array(
            'name'   => 'vendor',
            'label'  => fx::alang('Vendor', 'system'),
            'type'   => 'livesearch',
            'values' => array()
        );
        $vendor = fx::config('dev.vendor');
        if (!is_array($vendor)) {
            $vendor = explode(",", $vendor);
        }
        $vendor [] = 'my';
        foreach ($vendor as $num => &$part) {
            $part = trim($part);
            if (empty($part)) {
                unset($vendor[$num]);
            }
            $part = fx::util()->underscoreToCamel($part, true);
        }
        $vendor = array_unique($vendor);
        foreach ($vendor as $v) {
            $field['values'][]= array($v, $v);
        }
        $field['value'] = $vendor[0];
        return $field;
    }

    public static function addAdminFiles()
    {
        $path_floxim = fx::path('@floxim');
        $lang = fx::config('lang.admin');
        $js_files = array(
            FX_JQUERY_PATH,
            $path_floxim . '/lib/js/jquery.bem.js', // https://github.com/zenwalker/jquery-bem
            $path_floxim . '/Admin/js/fxj.js',
            $path_floxim . '/Admin/js/fx.js',
            $path_floxim . '/Admin/js/js-dictionary-' . $lang . '.js',
            FX_JQUERY_UI_PATH,
            $lang === 'en' ? null : $path_floxim.'/lib/js/jquery.datepicker.'.$lang.'.js',
            $path_floxim . '/lib/js/jquery.ba-hashchange.min.js',
            $path_floxim . '/lib/js/jquery.json-2.3.js',
            $path_floxim . '/lib/js/ajaxfileupload.js',
            $path_floxim . '/Admin/js-templates/jstx.js',
            'http://' . getenv("HTTP_HOST") . fx::path()->http($path_floxim).'/Admin/js-templates/compile.php',
            $path_floxim . '/Admin/js/lib.js',
            $path_floxim . '/Admin/js/sort.js',
            $path_floxim . '/Admin/js/front.js',
            $path_floxim . '/Admin/js/container.js',
            $path_floxim . '/Admin/js/adder.js',
            $path_floxim . '/Admin/js/buttons.js',
            $path_floxim . '/Admin/js/form.js',
            $path_floxim . '/Admin/js/debug.js',
            $path_floxim . '/Admin/js/livesearch.js',
            $path_floxim . '/Admin/js/suggest.js',
            $path_floxim . '/Admin/js/fields.js',
            $path_floxim . '/Admin/js/measures.js',
            $path_floxim . '/Admin/js/edit-in-place.js',
            $path_floxim . '/Admin/js/panel.js',
            $path_floxim . '/Admin/js/popup.js',
            $path_floxim . '/Admin/js/admin.js',
            $path_floxim . '/Admin/js/nav.js',
            $path_floxim . '/lib/editors/redactor/redactor.patched.js',
            //$path_floxim . '/lib/editors/redactor/redactor.min.js',
            $lang === 'en' ? null : $path_floxim.'/lib/editors/redactor/langs/'.$lang.'.js',
            $path_floxim . '/lib/editors/redactor/fontcolor.js',
            $path_floxim . '/lib/codemirror/codemirror.all.min.js',
            $path_floxim . '/lib/spectrum/spectrum.js',
            $path_floxim . '/lib/cropper/cropper.min.js',
            //$path_floxim . '/lib/cropper/cropper.js',
            $path_floxim . '/lib/js/jquery.form.js',
            $path_floxim . '/lib/js/jquery.cookie.js',
            $path_floxim . '/lib/js/jquery.ba-resize.min.js',
            $path_floxim . '/lib/js/jquery.scrollTo.js',
            $path_floxim . '/Admin/js/map.js',
            $path_floxim . '/Admin/js/node-panel.js',
            $path_floxim . '/Admin/js/condition-builder.js',
            $path_floxim . '/Admin/js/infoblock.js', // infoblock form overrides
            $path_floxim . '/lib/lessjs/less.min.js',
            $path_floxim . '/lib/tinycolor/tinycolor.js',
            $path_floxim . '/Admin/js/colorset.js',
            $path_floxim . '/Admin/js/font-preview.js'
        );
        $page = fx::page();


        $page->addJsBundle($js_files, array('name' => 'fx_admin'));
        
        $page->addCssFile('https://fonts.googleapis.com/css?family=Roboto:400,500,400italic,500italic,700,700italic&subset=latin,cyrillic');
        
        // todo: need fix path for css - now used server path
        $page->addCssFile( $path_floxim . '/lib/editors/redactor/redactor.css' );
        
        $page->addCssFile( $path_floxim . '/lib/spectrum/spectrum.css' );
        $page->addCssFile( $path_floxim . '/lib/cropper/cropper.min.css' );

        $page->addCssBundle(
            array(
                $path_floxim . '/Admin/style/mixins.less',
                $path_floxim . '/Admin/style/main.less',
                $path_floxim . '/Admin/style/backoffice.less',
                $path_floxim . '/Admin/style/forms.less',
                $path_floxim . '/Admin/style/front.less',
                $path_floxim . '/Admin/style/livesearch.less',
                $path_floxim . '/Admin/style/debug.less',
                $path_floxim . '/Admin/style/measures.less',
                $path_floxim . '/lib/codemirror/codemirror.css',
                $path_floxim . '/Admin/style/condition-builder.less'
            ), 
            array(
                'name' => 'admin'
            )
        );
    }

    /**
     * @return string
     */
    public function adminOffice()
    {
        self::addAdminFiles();

        if (fx::isAdmin()) {
            $panel = Adminpanel::panelHtml();
            $res = fx::template('@admin:back_office')->render(array('panel' => $panel));
            $js_config = new FxAdmin\Configjs();
            fx::page()->addJsText("\$fx.init(" . $js_config->getConfig() . ");");
        } else {
            $auth_form = fx::controller('floxim.user.user:auth_form')->render('floxim.user.user:auth_form');

            $recover_form = fx::controller(
                'floxim.user.user:recover_form',
                array('email' => isset($_POST['email']) ? $_POST['email'] : null)
            );
            $recover_form = $recover_form->render('floxim.user.user:recover_form');
            $res = fx::template('@admin:authorize')->render(array(
                'auth_form'    => $auth_form,
                'recover_form' => $recover_form
            ));
        }
        return fx::page()->postProcess($res);
    }
    
    /**
     * pass entity type, entity id and previous item id:
     * {
     *  action: "move"
     *  component_id: 23
     *  entity: "field"
     *  id: 431
     *  mode: "relative"
     *  move_after: 347
     * }
     */
    public function move($input)
    {
        $finder = fx::data($input['entity']);
        $finder->moveAfter($input['id'], $input['move_after'], $input['params']);
    }

    /**
     * old move implementation - pass all sorted items
     */
    public function moveSave($input)
    {
        if (isset($input['mode']) && $input['mode'] === 'relative') {
            return $this->move($input);
        }

        $entity = $this->entity_type;

        $positions = $input['positions'] ? $input['positions'] : $input['pos'];
        if ($positions) {
            $priority = 0;
            foreach ($positions as $id) {
                $item = fx::data($entity)->getById($id);
                if ($item) {
                    $item->set('priority', $priority++)->save();
                }
            }
        }

        return array('status' => 'ok');
    }

    public function deleteSave($input)
    {

        $es = $this->entity_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                fx::data($es, $id)->delete();
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }
        return $result;
    }
}
