<?php

/**
 * @todo link to jump
 * there is still action - upload - not currently used
 */
class fx_controller_admin_module_filemanager extends fx_controller_admin_module {

    public function init_menu() {
        $this->add_node('filemanager', fx::alang('File-manager','system'), 'module_filemanager.ls');
    }

    protected $root_name = 'root'; // name of the root path
    protected $base_path = ''; // the path to the root, above do not get out
    protected $file_filters = array(); // the filters which files show - denial: "!~\.php$~i"
    protected $base_url_template = '#admin.module_filemanager.#action#(#params#)'; // template for the url lookup - #action# and #params#
    protected $path = false; // current directory
    protected $breadcrumb_target = false;

    public function process() {

        $input = $this->inout;
        $action = $this->action;
        $do_return = $this->process_do_return;
        
    	$this->path = isset($input['path']) ? $input['path'] : $input['params'][0];
    	$this->path = trim($this->path, "/\\");

    	$this->base_path = isset($input['base_path']) ? $input['base_path'] : '';
    	$this->base_path = trim($this->base_path, "/\\");

    	if (!empty($this->base_path)) {
    		$this->path = $this->base_path.'/'.$this->path;
    		$this->path = trim($this->path, "/\\");
    	}

    	foreach (array('root_name', 'file_filters', 'base_url_template') as $prop) {
    		if (isset($input[$prop])) {
				$this->$prop = $input[$prop];
			}
    	}

    	if (isset($input['breadcrumb_target']) && is_object($input['breadcrumb_target'])) {
    		$this->breadcrumb_target = $input['breadcrumb_target'];
    	}

        return parent::process($input, $action, $do_return);
    }

    // trim the path using $base_path
    protected function _trim_path($path) {
    	$path = trim($path, "/\\");
    	$path = preg_replace("~^".preg_quote($this->base_path, "/\\")."~", '', $path);
    	$path = trim($path, "/\\");
    	return $path;
    }

    // Get the url for action
    protected function _get_url($action, $params = false) {
    	if ($params === false) {
    		$params = array();
    	} elseif (!is_array($params)) {
    		$params = array($params);
    	}
    	$tpl = $this->base_url_template;
    	$url = str_replace("#action#", $action, $tpl);
    	$url = str_replace("#params#", join(",", $params), $url);
    	return $url;
    }

    protected function _filter_file($file) {
    	foreach ($this->file_filters as $f) {
    		$inverse = preg_match("~^!~", $f);
    		$f = preg_replace("~^!~", '', $f);
    		$match = preg_match($f, $file);
    		$c_res = ($inverse ? !$match : $match);
    		if (!$c_res) {
    			return false;
    		}
    	}
    	return true;
    }

    /**
     * listing directory
     */
    public function ls($input) {

        // the directory that is being viewed
        //$dir = $this->path;
        $dir = isset($input['params'][0]) ? $input['params'][0] : false;
        // catalogue without base (with restrictions)
        $rel_dir = $this->_trim_path($dir);

        // bread crumbs #2 (path)
        // if there's this->breadcrumb_target - will return false, adding bread crumbs right there
        if ( ($breadcrumb = $this->get_breadcrumb($rel_dir)) ) {
			$fields[] = $this->ui->label($breadcrumb.'<br>');
		}

        $ar = array('type' => 'list', 'filter' => true);
        $ar['labels'] = array('name' => FX_ADMIN_NAME, 'type' => fx::alang('Type','system'), 'size' => fx::alang('Size','system'), 'permission' => fx::alang('Permissions','system'));

        $ls_res = fx::files()->ls(($dir ? $dir : '/'), false, true);
        if ($dir && $rel_dir) {
            $pos = strrpos($dir, '/');
            $parent_dir = $pos ? substr($dir, 0, $pos) : '';
            $ar['values'][] = array(
            	'name' => array(
            		'name' => fx::alang('Parent directory','system'),
            		'url' => $this->_get_url('ls', $this->_trim_path($parent_dir)) //'module_filemanager.ls('.$parent_dir.')'
            	)
            );
        }

        if ($ls_res) {
            foreach ($ls_res as $v) {
            	if (!$this->_filter_file($v['name'])) {
            		continue;
            	}
                $path = ($dir ? $dir.'/' : '').$v['name'];
                $perm = fx::files()->get_perm($path, true);
                if (!$v['dir']) {
                    $size = fx::files()->filesize($path);
                    if ($size < 1e3) {
                        $size .= ' ' . fx::alang('byte','system');
                    } elseif ($size < 1e6) {
                        $size = number_format($size / 1e3, 1).' ' . fx::alang('Kb','system');
                    } elseif ($size < 1e9) {
                        $size = number_format($size / 1e6, 1).' ' . fx::alang('Mb','system');
                    } else {
                        $size = number_format($size / 1e9, 1).' ' . fx::alang('Gb','system');
                    }
                }
                $item_action = $v['dir'] ? 'ls' : 'editor';
                $ar['values'][] = array(
                	'id' => $path,
					'name' => array('name' => $v['name'], 'url' => $this->_get_url($item_action, $this->_trim_path($path))),
					'size' => ($v['dir'] ? ' - ' : $size),
					'type' => ($v['dir'] ? fx::alang('directory','system') : fx::alang('File','system')),
					'permission' => $perm
				);
            }
        }


        $fields[] = $ar;

        $this->response->add_fields($fields);
        $this->response->add_buttons("add,edit,delete");
        $this->response->add_button_options('add', array('dir' => $dir));
        $this->response->submenu->set_menu('tools')->set_subactive('filemanager');
    }

    public function editor($input) {
        // the directory that is being viewed
        $filename = isset($input['params'][0]) ? $input['params'][0] : false ;
        //$filename = $this->path;

        if (!$filename) {
            $result['status'] = 'error';
            $result['text'] = fx::alang('Do not pass the file name!','system');
            return $result;
        }

        // bread crumbs #2 (path)
        if ( ($breadcrumb = $this->get_breadcrumb($this->_trim_path($filename))) ) {
        	$fields []= $this->ui->label($breadcrumb."<br>");
        }

        $content_type = fx::files()->mime_content_type($filename);
        $is_image = strpos($content_type, 'image/') === 0 || $content_type == 'application/ico';

        if ($is_image) {
            $fields[] = $this->ui->label('<img src="'.fx::config()->SUB_FOLDER.'/'.$filename.'"/>');
        } else {
        	$file_content = fx::files()->readfile($filename);
            if ($file_content !== null) {
            	$content_field = array('name' => 'file_content', 'type' => 'text', 'value' => $file_content);
            	preg_match("~\.([a-z]{1,4})$~", $filename, $ext);
            	if (isset($ext[1]) && !empty($ext[1])) {
					$content_field['code'] = $ext[1];
            	}
                $fields[] = $content_field;
                $fields[] = array('type' => 'hidden', 'name' => 'file_name', 'value' => $filename);
                $fields[] = array('type' => 'hidden', 'name' => 'action', 'value' => 'editor');
            } else {
                $fields[] = $this->ui->error( fx::alang('Reading of file failed','system') );
            }
        }
        $fields[]= array('type' => 'hidden', 'name' => 'essence', 'value' => 'module_filemanager');
        $fields[]= array('type' => 'hidden', 'name' => 'fx_admin', 'value' => true);
        $fields[] = array('type' => 'hidden', 'name' => 'posting', 'value' => 1);
        $this->response->add_fields($fields);
        $this->response->submenu->set_menu('tools')->set_subactive('filemanager');
        $perms = fx::files()->get_perm($filename);
        $this->response->add_form_button('save');
    }

    public function editor_save($input) {
        $result = array('status' => 'ok');

        /* checks */
        if (!isset($input['file_content']) || !isset($input['file_name'])) {
            $result['status'] = 'error';
            $result['text'] = fx::alang('Not all fields are transferred!','system');
        } else {
            $res = fx::files()->writefile($input['file_name'], $input['file_content'], false);
            if ($res !== 0) {
                $result['status'] = 'error';
                $result['text'] = fx::alang('Writing to file failed','system');
            }
        }
        return $result;
    }

    public function add($input) {
        $fields[] = array(
            'type' => 'select',
            'name' => 'type',
            'values' => array(
                'file' => fx::alang('File','system'),
                'dir' => fx::alang('directory','system')
            ), 
            'label' => fx::alang('What we create','system')
        );
        $fields[] = array('name' => 'name', 'label' => fx::alang('Name of file/directory','system'));
        $fields[] = array('type' => 'hidden', 'name' => 'dir', 'value' => $input['dir']);
        $fields[] = array('type' => 'hidden', 'name' => 'posting', 'value' => 1);
        $fields[] = array('type' => 'hidden', 'name' => 'action', 'value' => 'add');
        $result = array('fields' => $fields);
        $result['dialog_title'] = fx::alang('Create a new file/directory','system');
        return $result;
    }

    public function add_save($input) {
        $result = array('status' => 'ok');

        /* checks */
        if (!$input['name']) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Enter the name of the file/directory','system');
            $result['fields'][] = 'name';
        }
        if (!isset($input['dir'])) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Not all fields are transferred','system');
            $result['fields'][] = 'name';
        }

        if ($result['status'] == 'ok') {
            if ($input['type'] == 'dir') {
                $res = fx::files()->mkdir($input['dir'].'/'.$input['name'], false);
            } else {
                $res = fx::files()->writefile($input['dir'].'/'.$input['name'], '', false);
            }
            if ($res !== 0) {
                $result['status'] = 'error';
                $result['text'][] = fx::alang('An error occurred while creating the file/directory','system');
            } elseif ($input['type'] != 'dir') {
                $result['location'] = 'tools.module_filemanager.editor('.$input['dir'].'/'.$input['name'].')';
            }
        }

        return $result;
    }

    public function edit($input) {
        $filename = $input['file_name'];
        if (!$filename) {
            $result['status'] = 'error';
            $result['text'] = fx::alang('Do not pass the file name!','system');
        }

        $pos = strrpos($filename, '/');
        $only_name = ($pos !== false) ? substr($filename, $pos + 1) : $filename;

        $perms = fx::files()->get_perm($filename);
        $is_dir = ($perms & 0x4000) == 0x4000;
        $perms = $perms & 0777;

        $fields[] = array('name' => 'name', 'label' => fx::alang('Name','system'), 'value' => $only_name);

        $active_perm = array();
        if ($perms & 0400) $active_perm[] = 'r';
        if ($perms & 0200) $active_perm[] = 'w';
        if ($perms & 0100) $active_perm[] = 'x';
        $fields[] = array('name' => 'perm_user', 'type' => 'checkbox',
                'label' => fx::alang('Permissions for the user owner','system'),
                'values' => array(
                        'r' => fx::alang('Reading','system'),
                        'w' => fx::alang('Writing','system'),
                        'x' => ($is_dir ? fx::alang('View the contents','system') : fx::alang('Execution','system')),
                ), 'value' => $active_perm);

        $active_perm = array();
        if ($perms & 040) $active_perm[] = 'r';
        if ($perms & 020) $active_perm[] = 'w';
        if ($perms & 010) $active_perm[] = 'x';
        $fields[] = array('name' => 'perm_group', 'type' => 'checkbox',
                'label' => fx::alang('Permissions for the group owner','system'),
                'values' => array(
                        'r' => fx::alang('Reading','system'),
                        'w' => fx::alang('Writing','system'),
                        'x' => ($is_dir ? fx::alang('View the contents','system') : fx::alang('Execution','system')),
                ), 'value' => $active_perm);

        $active_perm = array();
        if ($perms & 04) $active_perm[] = 'r';
        if ($perms & 02) $active_perm[] = 'w';
        if ($perms & 01) $active_perm[] = 'x';
        $fields[] = array('name' => 'perm_other', 'type' => 'checkbox',
                'label' => fx::alang('Permissions for the rest','system'),
                'values' => array(
                        'r' => fx::alang('Reading','system'),
                        'w' => fx::alang('Writing','system'),
                        'x' => ($is_dir ? fx::alang('View the contents','system') : fx::alang('Execution','system')),
                ), 'value' => $active_perm);

        $fields[] = array('type' => 'hidden', 'name' => 'filename', 'value' => $filename);
        $fields[] = array('type' => 'hidden', 'name' => 'posting', 'value' => 1);
        $fields[] = array('type' => 'hidden', 'name' => 'action', 'value' => 'edit');
        $result = array('fields' => $fields);
        $result['dialog_title'] = fx::alang('Edit the file/directory','system');
        return $result;
    }

    public function edit_save($input) {
        $filename = $input['filename'];
        if (!$filename) {
            return array('status' => 'error', 'text' => fx::alang('Not all fields are transferred!','system'));
        }
        $result = array('status' => 'ok');

        /* checks */
        if (!$input['name']) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Enter the name','system');
            $result['fields'][] = 'name';
        }
        if (!$input['perm_user'] && !$input['perm_group'] && !$input['perm_other']) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Set permissions','system');
            $result['fields'][] = 'perm_user';
        }

        if ($result['status'] == 'ok') {  // change the rights
            $perms = 0;

            if ($input['perm_user']) {
                if (in_array('r', $input['perm_user'])) $perms += 0400;
                if (in_array('w', $input['perm_user'])) $perms += 0200;
                if (in_array('x', $input['perm_user'])) $perms += 0100;
            }

            if ($input['perm_group']) {
                if (in_array('r', $input['perm_group'])) $perms += 040;
                if (in_array('w', $input['perm_group'])) $perms += 020;
                if (in_array('x', $input['perm_group'])) $perms += 010;
            }

            if ($input['perm_other']) {
                if (in_array('r', $input['perm_other'])) $perms += 04;
                if (in_array('w', $input['perm_other'])) $perms += 02;
                if (in_array('x', $input['perm_other'])) $perms += 01;
            }

            $old_perms = fx::files()->get_perm($filename) & 0777;
            if ($old_perms != $perms) {
                $res = fx::files()->chmod($filename, $perms);
                if ($res !== 0) {
                    $result = array('status' => 'error');
                    $result['text'][] = fx::alang('Error when permission','system');
                }
            }
        }

        if ($result['status'] == 'ok') {  // rename
            $pos = strrpos($filename, '/');
            $old_name = ($pos !== false) ? substr($filename, $pos + 1) : $filename;

            if ($old_name != $input['name']) {
                $res = fx::files()->rename($filename, $input['name']);
                if ($res !== 0) {
                    $result = array('status' => 'error');
                    $result['text'][] = fx::alang('Error when changing the name','system');
                }
            }
        }

        return $result;
    }

    public function delete_save($input) {
        if ($input['id']) {
            $filename = $input['id'];
            if (is_array($filename)) {
                foreach ($filename as $i => $v) {
                    $filename[$i] = $v;
                }
            } else {
                $filename = array($filename);
            }

            foreach ($filename as $v) {
                $res = fx::files()->rm($v);
                if ($res !== 0) {
                    $result = array('status' => 'error');
                    $result['text'][] = fx::alang('Error Deleting File','system') .' "'.$v. '"';
                    break;
                }
            }
        }
    }

    public function upload($input) {
        $fields[] = array('type' => 'file', 'name' => 'file',
                'label' => fx::alang('Upload file','system'));
        $fields[] = array('type' => 'hidden', 'name' => 'dir', 'value' => $input['dir']);
        $fields[] = array('type' => 'hidden', 'name' => 'posting', 'value' => 1);
        $fields[] = array('type' => 'hidden', 'name' => 'action', 'value' => 'upload');
        return array('fields' => $fields);
    }

    public function upload_save($input) {
        if (!isset($input['dir'])) {
            return array('status' => 'error', 'text' => fx::alang('Not all fields are transferred!','system'));
        }
        $dir = $input['dir'];
        $result = array('status' => 'ok');

        /* checks */
        if (!$input['file']) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Enter the file','system');
            $result['fields'][] = 'file';
        }

        if ($result['status'] == 'ok') {
            $file = $input['file'];

            $res = fx::files()->move_uploaded_file($file['tmp_name'], $dir.'/'.$file['name']);
            if ($res !== 0) {
                $result = array('status' => 'error');
                $result['text'][] = fx::alang('Error when downloading a file','system');
            }
        }

        return $result;
    }

    protected function get_breadcrumb($url) {
        foreach ($this->get_breadcrumb_arr($url) as $v) {
        	if ($this->breadcrumb_target) {
        		$this->breadcrumb_target->add_item($v['name'], $v['url']);
        		continue;
        	}
            if ($v['url']) {
				$breadcrumbs[] = '<a href="'.$v['url'].'">'.$v['name'].'</a>';
            } else {
            	$breadcrumbs[] = $v['name'];
            }
        }
        if ($this->breadcrumb_target) {
        	return false;
        }
        return join(' / ', $breadcrumbs);
    }

    private function get_breadcrumb_arr($url) {
        $breadcrumb = array();
        // if there is bread crumbs parent - not copy the root
        if (!$this->breadcrumb_target) {
        	$breadcrumb []= array(
        		'name' => '<i>'.$this->root_name.'</i>',
        		'url' => $this->_get_url('ls') //'#admin.manage.tools.module_filemanager.ls()'
        	);
        }
        if ($url) {
            $dir_pieces = explode('/', $url);
            foreach ($dir_pieces as $v) {
                $path_piece .= $path_piece ? '/'.$v : $v;
                $breadcrumb[] = array(
                	'name' => $v,
                	'url' => $this->_get_url('ls', $path_piece) //'#admin.manage.tools.module_filemanager.ls('.$path_piece.')'
                );
            }
        }
        unset($breadcrumb[count($breadcrumb) - 1]['url']);  // last - not a link
        return $breadcrumb;
    }

    public function download($input) {
        $filename = $input['id'];
        $pos = strrpos($filename, '/');
        $only_name = ($pos !== false) ? substr($filename, $pos + 1) : $filename;
        $file_size = fx::files()->filesize($filename);
        $file_content = fx::files()->readfile($filename);
        if ($file_content !== null) {
            while (ob_get_level()) {
                @ob_end_clean();
            }
            header($_SERVER['SERVER_PROTOCOL']." 200 OK");
            
            header("Content-type: ".fx::files()->mime_content_type($filename));
            header("Content-Disposition: attachment; filename=\"".urldecode($only_name)."\"");
            header('Content-Transfer-Encoding: binary');

            if ($file_size) {
                header("Content-Length: ".$file_size);
                header("Connection: close");
            }
            echo $file_content;
            die;
        } else {
            $result['status'] = 'error';
            $result['text'] = fx::alang('Could not open file!','system');
            return $result;
        }
    }

}
