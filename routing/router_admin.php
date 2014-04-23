<?php
class fx_router_admin extends fx_router {
    
    public function route($url = null, $context = null) {
        $regexp = "/((floxim\/)+|(floxim\/index.php)+)$/";
        if (!preg_match($regexp, $url)) {
            return null;
        }
        $input = fx::input()->make_input();
        

        $essence = fx::input()->fetch_post('essence');
        $action = fx::input()->fetch_post('action');
        
        if (!$essence || !$action) {
            return new fx_controller_admin();
        }
        
        $posting = fx::input()->fetch_post('posting');
        if (!preg_match("~^module_~", $essence) || fx::input()->fetch_post('fx_admin')) {
            $essence = 'admin_'.$essence;
        }
        if ($posting && $posting !== 'false') {
            $action .= "_save";
        }
        
        $classname = 'fx_controller_' . $essence;
       
        try {
            $controller = new $classname($input, $action);
        } catch (Exception $e) {
            die("Error! Essence: " . htmlspecialchars($essence));
        }
        return $controller;
    }
}