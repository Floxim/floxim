<?php
class fx_controller_component_comment extends fx_controller_component
{
    
    protected function _get_target_infoblock()
    {
        $target_ibs = fx::data('infoblock')->where('controller', 'component_comment')->where('action', 'listing')->all();
        $field      = array(
            'type' => 'select',
            'name' => 'target_infoblock_id',
            'label' => 'Target infoblock',
            'values' => array()
            
        );
        foreach ($target_ibs as $ib) {
            $field['values'][] = array(
                $ib['id'],
                $ib['name']
            );
        }
        return $field;
    }
    public function get_finder()
    {
        $finder = parent::get_finder();
        
        if (!fx::is_admin()) {
            if ($own_comments = $this->_get_own_comments()) {
                $finder->where_or(array(
                    'is_moderated',
                    1
                ), array(
                    'id',
                    $own_comments
                ));
            } else {
                $finder->where('is_moderated', 1);
            }
        }
        return $finder;
    }
    
    public function do_add()
    {
        if (isset($_POST["addcomment"]) && isset($_POST["user_name"]) && !empty($_POST["user_name"]) && isset($_POST["comment_text"]) && !empty($_POST["comment_text"])) {
            
            $comments = fx::data('content_comment')->create(array(
                'user_name' => $_POST["user_name"],
                'comment_text' => $_POST["comment_text"],
                'publish_date' => date("Y-m-d H:i:s"),
                'parent_id' => $this->_get_parent_id(),
                'infoblock_id' => $this->get_param('target_infoblock_id')
            ));
            $comments->save();
            if (!isset($_COOKIE["own_comments"])) {
                setcookie('own_comments', $comments["id"], time() + 60 * 60 * 24 * 30);
            } else {
                setcookie('own_comments', $_COOKIE["own_comments"] . ',' . $comments["id"], time() + 60 * 60 * 24 * 30);
            }
            fx::http()->refresh();
        }
    }
    
    protected function _get_own_comments()
    {
        if (isset($_COOKIE["own_comments"])) {
            return explode(',', $_COOKIE["own_comments"]);
        }
        return;
        
    }
    
}
?>