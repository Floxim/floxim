<?php
class fx_system_files {
    public $ftp_host;
    public $ftp_port;
    public $ftp_path;
    public $ftp_user;
    public $new_file_mods;
    public $new_dir_mods;
    protected $password;
    protected $base_url;
    protected $base_path;
    protected $tmp_files;

    protected function mkdir_ftp($path, $recursive = true) {
        if (!$path) {
            return 1;
        }

        $parent_path = dirname($path);

        if (!is_dir($this->base_path.$parent_path)) {
            if ($recursive) {
                $res = $this->mkdir($parent_path, true);
                if ($res) {
                    return $res;
                }
            } else {
                return 2;
            }
        }

        $dir_name = basename($path);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.'/'.$parent_path.'/');
        curl_setopt($ch, CURLOPT_POSTQUOTE, array(
                "MKD ".$dir_name,
                "SITE CHMOD ".sprintf("%o", $this->new_dir_mods)." ".$dir_name)
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (curl_exec($ch) !== false) {
            curl_close($ch);
            return 0;
        } else {
            $info = curl_getinfo($ch);
            curl_close($ch);
            return $info["http_code"];
        }
    }

    protected function ls_not_recursive($path) {
        if (!$path) {
            return null;
        }

        $local_path = realpath($this->base_path.$path);
        if (!$local_path) {
            return null;
        }

        $result = array();

        $handle = opendir($local_path);
        if ($handle) {
            while (($file = readdir($handle)) !== false) {
                if ($file != "." && $file != "..") {
                    $file_path = realpath($local_path.'/'.$file);
                    $result[] = array(
                            "name" => $file,
                            "path" => $file_path,
                            "dir" => is_dir($file_path) ? 1 : 0
                    );
                }
            }
            closedir($handle);
        }
        return $result;
    }

    protected function ls_recursive($path) {
        if (!$path) {
            return null;
        }

        $local_path = realpath($this->base_path.$path);
        if (!$local_path) {
            return null;
        }

        $dirs = array();
        $result = array();

        array_push($dirs, $local_path.'/');

        while ($dir = array_pop($dirs)) {
            $handle = opendir($dir);
            if ($handle) {
                while (($file = readdir($handle)) !== false) {
                    if ($file != "." && $file != "..") {
                        $file_path = realpath($dir.$file);
                        $is_dir = is_dir($file_path) ? 1 : 0;
                        $result[] = array(
                                "name" => $file,
                                "path" => $file_path,
                                "dir" => $is_dir
                        );
                        if ($is_dir) {
                            array_push($dirs, $file_path.'/');
                        }
                    }
                }
                closedir($handle);
            }
        }

        return $result;
    }

    protected function chmod_not_recursive($filename, $perms) {
        if (!$filename) {
            return 1;
        }

        $local_filename = $this->base_path.$filename;

        if (!file_exists($local_filename)) {
            return 1;
        }

        // try to change it via the local filesystem
        if (@chmod($local_filename, $perms)) {
            return 0;
        }

        // in case of failure break ftp
        return $this->chmod_ftp(dirname($filename), array(basename($filename)), $perms);
    }

    /**
     * Changes in the specified directory rights specified files
     * @param string $dir catalog, which are files
     * @param array $files array of file names (names only, without the path)
     * @param array $files new access rights (to remember about octal notation)
     * @return int 0 on success, otherwise, ftp error code
     */
    protected function chmod_ftp($dir, $files, $perms) {
        if (!$files || empty($files)) {
            return 1;
        }

        $ftp_pathname = $this->base_url.'/'.$dir.'/';
        $ftp_cmds = array();
        foreach ($files as $file) {
            $ftp_cmds[] = "SITE CHMOD ".sprintf("%o", $perms)." ".$file;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ftp_pathname);
        curl_setopt($ch, CURLOPT_POSTQUOTE, $ftp_cmds);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (curl_exec($ch) !== false) {
            curl_close($ch);
            return 0;
        } else {
            $info = curl_getinfo($ch);
            curl_close($ch);
            return $info["http_code"];
        }
    }

    protected function chmod_recursive($filename, $perms) {
        if (!$filename) {
            return 1;
        }

        $local_filename = $this->base_path.$filename;

        if (!file_exists($local_filename)) {
            return 1;
        }

        if (!is_dir($local_filename)) {
            return $this->chmod_not_recursive($filename, $perms);
        }

        $result = 0;

        $dirs = array();

        if (!@chmod($local_filename, $perms)) {
            $result |= $this->chmod_ftp(dirname($filename), array(basename($filename)), $perms);
        }

        array_push($dirs, $filename.'/');

        while ($dir = array_pop($dirs)) {
            $handle = @opendir(realpath($this->base_path.$dir));
            if ($handle) {
                $chmod_failed = array();  // that could not change it via the local filesystem
                while (($file = readdir($handle)) !== false) {
                    if ($file != "." && $file != "..") {
                        $file_path = $dir.$file;
                        $local_file_path = $this->base_path.$file_path;

                        // try to change it via the local filesystem
                        if (!@chmod(realpath($local_file_path), $perms)) {
                            $chmod_failed[] = $file;
                        }

                        if (is_dir($local_file_path)) {
                            array_push($dirs, $file_path.'/');
                        }
                    }
                }
                closedir($handle);

                if (!empty($chmod_failed)) {
                    $result |= $this->chmod_ftp($dir, $chmod_failed, $perms);
                }
            }
        }

        return $result;
    }

    protected function _copy_file($local_old_filename, $local_new_filename) {
        $res = @copy($local_old_filename, $local_new_filename);

        if ($res !== false) {
            @chmod($local_new_filename, $this->new_file_mods);
            return 0;
        }

        $content = $this->readfile($old_filename);
        if ($content !== null) {
            $res = $this->writefile($new_filename, $content);
        }

        return $res;
    }

    /*
     * @param string the login name for the ftp user
     * @param string the password of the ftp user
     * @param string the name of the ftp host
     * @param string the ftp port
     * @param string the ftp directory with root
     * @return object
     */

    public function __construct($user = '', $password = '', $host = null, $port = 21, $ftp_path = '') {
        
        $this->allowed_extensions = explode(",", 'gif,jpg,png,ico,rar,zip,doc,pdf,txt,xls,jpe,jpeg,flv,swf,asf,ppt,mp3');
	$this->image_extensions = explode(',', 'gif,jpg,jpe,jpeg,png,ico');
        
        $this->ftp_user = $user;
        $this->ftp_password = $password;
        $this->ftp_port = $port;
        if ($ftp_path && ($ftp_path[strlen($ftp_path) - 1] == '/')) {
            $ftp_path = substr($ftp_path, 0, -1);
        }
        if ($ftp_path && $ftp_path[0] != '/') {
            $ftp_path = '/'.$ftp_path;
        }
        $this->ftp_path = $ftp_path;
        $this->ftp_host = $host ? $host : $_SERVER['HTTP_HOST'];
        $this->base_url = "ftp://".$this->ftp_user.":".$this->ftp_password."@".
                $this->ftp_host.":".$this->ftp_port."/".$this->ftp_path;
        $this->base_path = fx::path('root');
        $this->new_file_mods = 0666;
        $this->new_dir_mods = 0777;

        $this->tmp_files = array();
    }

    public function __destruct() {
        foreach ($this->tmp_files as $f) {
            $this->rm($f);
        }
    }
    
    public function open($filename, $mode = 'w') {
        $filename = fx::path()->to_abs($filename);
        $dir = dirname($filename);
        if (!file_exists($dir)) {
            $this->mkdir($dir);
        }
        $fh = fopen($filename, $mode);
        if (!$fh) {
            throw new fx_exception_files('Can not open file '.$filename);
        }
        return $fh;
    }

    public function writefile($filename, $filedata = '', $make_dir = true) {
        $fh = $this->open($filename);
        fputs($fh, $filedata);
        fclose($fh);
        return 0;
        
        if (!$filename) {
            return 1;
        }

        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }

        $local_filename = $this->base_path.$filename;

        // Check whether there is a directory and creates it if you can
        if ($local_filename && file_exists($local_filename)) {
            $path_success = is_writeable($local_filename);
        } else {
            $path_success = false;
            $filepath = dirname($filename);
            $local_filepath = dirname($local_filename);
            if (!is_dir($local_filepath)) {
                if ($make_dir) {
                    $this->mkdir($filepath, true);
                }
            }

            $path_success = is_writeable($local_filepath);
        }

        if ($path_success) {
            // Try to write via the local filesystem
            $file = @fopen($local_filename, "w");
            if ($file) {
                $success = !(fwrite($file, $filedata) === false);
                fclose($file);
                @chmod($file, $this->new_file_mods);
            }
        }

        if (isset($success) && ($success !== false)) {
            return 0;
        }

        throw new fx_exception_files( fx::alang('File is not writable','system') . ' ' . $filename);

        // If not, write via ftp
        $tmpfile = tmpfile();
        fwrite($tmpfile, $filedata);
        fseek($tmpfile, 0);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$filename);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $tmpfile);
        curl_setopt($ch, CURLOPT_POSTQUOTE, array("SITE CHMOD ".sprintf("%o", $this->new_file_mods)." ".$this->ftp_path.$filename));
        curl_setopt($ch, CURLOPT_TRANSFERTEXT, 1);
        if (curl_exec($ch) !== false) {
            curl_close($ch);
            fclose($tmpfile);
            return 0;
        } else {
            $info = curl_getinfo($ch);
            curl_close($ch);
            fclose($tmpfile);
            return $info["http_code"];
        }
    }

    public function chmod($filename, $perms, $recursive = false) {
        if (!$filename) {
            return 1;
        }

        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }

        if ($recursive) {
            return $this->chmod_recursive($filename, $perms);
        } else {
            return $this->chmod_not_recursive($filename, $perms);
        }
    }

    public function mkdir($path, $recursive = true) {
        if (!$path) {
            return 1;
        }
        $local_path = fx::path()->to_abs($path);
        if (is_dir($local_path)) {
            return 0;
        }

        // first, try through the Federal Assembly
        if (@mkdir($local_path, $this->new_dir_mods, $recursive)) {
            chmod($local_path, $this->new_dir_mods);
            return true;
        }
        else {
            throw new fx_exception_files("Cannot create directory ".$path);
        }

        return 1;
        // try to ftp
        return $this->mkdir_ftp($path, $recursive);
    }

    /**
     * The file read
     * @param string $filename the file name
     */
    public function readfile($filename) {

    	$local_filename = $this->get_full_path($filename);

        // Check for the existence, the ability to read and is not a directory
        if (!file_exists($local_filename) || !is_readable($local_filename) || is_dir($local_filename)) {
            throw new fx_exception_files("Unable to read file $local_filename");
        }

        return file_get_contents($local_filename);
    }

    public function ls($path, $recursive = false, $sort = false) {
        if (!$path) {
            return null;
        }

        if ($path[0] != '/') {
            $path = '/'.$path;
        }

        $local_path = $this->base_path.$path;

        if (!is_dir($local_path) || !is_readable($local_path)) {
            return null;
        }

        if (!$recursive) {
            $result = $this->ls_not_recursive($path);
        } else {
            $result = $this->ls_recursive($path);
        }

        if ($sort && !empty($result)) {
            // helpfull arrays to sorting
            $dirs = $names = array();
            // get arrays
            foreach ($result as $file) {
                $dirs[] = $file['dir'];
                $names[] = $file['name'];
            }
            // sorting
            array_multisort($dirs, SORT_DESC, SORT_NUMERIC, $names, SORT_ASC, SORT_STRING, $result);
        }

        return $result;
    }

    protected function rm_ftp($dir, $files) {  // only empty
        if (!$files || empty($files)) {
            return 1;
        }

        $ftp_pathname = $this->base_url.'/'.$dir.'/';
        $ftp_cmds = array();
        foreach ($files as $file) {
            $ftp_cmds[] = "DELE ".$file;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ftp_pathname);
        curl_setopt($ch, CURLOPT_POSTQUOTE, $ftp_cmds);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (curl_exec($ch) !== false) {
            curl_close($ch);
            return 0;
        } else {
            $info = curl_getinfo($ch);
            curl_close($ch);
            return $info["http_code"];
        }
    }

    public function rm($filename) {
        if (is_array($filename)) {
            foreach ($filename as $file) {
                $result = $this->rm($file);
            }

            return 0;
        }

        if (!$filename) {
            return 1;
        }

        $local_filename = fx::path()->to_abs($filename);

        if (!file_exists($local_filename)) {
            return 1;
        }

        $result = 0;

        if (is_dir($local_filename)) {

            $filename=rtrim($filename,'/').'/';
            $local_filename=rtrim($local_filename,'/').'/';

            $handle = opendir($local_filename);
            if ($handle) {
                $failed_files = array();  // that could not be removed via the local filesystem
                while (($file = readdir($handle)) !== false) {
                    if ($file != "." && $file != "..") {

                        $local_file = $local_filename.$file;

                        if (is_dir($local_file)) {
                            $result |= $this->rm($filename.$file);
                        } else {
                            if (!@unlink($local_file)) {  // try to delete via the local filesystem
                                $failed_files[] = $file;
                            }
                        }
                    }
                }
                closedir($handle);

                if (!empty($failed_files)) {
                    $result |= $this->rm_ftp($filename, $failed_files);
                }
            }

            if (is_writable($local_filename)) {
                $success = @rmdir($local_filename);
            }

            if (isset($success) && $success) {
                return 0;
            } else {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->base_url.'/'.dirname($filename).'/');
                curl_setopt($ch, CURLOPT_POSTQUOTE, array("RMD ".basename($filename)));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                if (curl_exec($ch) !== false) {
                    curl_close($ch);
                    return $result;
                } else {
                    $info = curl_getinfo($ch);
                    curl_close($ch);
                    return $result | $info["http_code"];
                }
            }
        } else {
            if (is_writable($local_filename)) {
                $success = @unlink($local_filename);
            }

            if (isset($success) && $success) {
                fx::trigger('unlink', array('file' => $local_filename));
                return 0;
            }
            return $this->rm_ftp(dirname($filename), array(basename($filename)));
        }
    }

    public function get_perm($filename, $ret_str = false) {

        if (!$filename) {
            return null;
        }

        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }

        $local_filename = $this->base_path.$filename;

        // Check the existence and reading
        if (!file_exists($local_filename) || !is_readable($local_filename)) {
            return null;
        }

        $perms = fileperms($local_filename);
        if ($ret_str) {
            $perms = $perms & 0777;
            $res = ($perms & 0400) ? 'r' : '-';
            $res .= ($perms & 0200) ? 'w' : '-';
            $res .= ($perms & 0100) ? 'x' : '-';
            $res .= ($perms & 040) ? 'r' : '-';
            $res .= ($perms & 020) ? 'w' : '-';
            $res .= ($perms & 010) ? 'x' : '-';
            $res .= ($perms & 04) ? 'r' : '-';
            $res .= ($perms & 02) ? 'w' : '-';
            $res .= ($perms & 01) ? 'x' : '-';
            return $res;
        } else {
            return $perms;
        }
    }

    public function rename($filename, $new_filename) {

        $new_filename = trim($new_filename, "/");

        if (!$filename || !$new_filename) {
            return null;
        }

        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }

        $local_filename = $this->base_path.$filename;
        $local_new_filename = dirname($local_filename).'/'.$new_filename;

        if (!file_exists($local_filename)) {
            return 1;
        }

        if (is_writable($local_filename)) {
            $success = @rename($local_filename, $local_new_filename);
        }

        if (isset($success) && $success) {
            return 0;
        }

        $ftp_path = $this->base_url.'/'.dirname($filename).'/';

        // try to rename ftp
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ftp_path);
        curl_setopt($ch, CURLOPT_POSTQUOTE, array("RNFR ".basename($filename), "RNTO ".$new_filename));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (curl_exec($ch) !== false) {
            curl_close($ch);
            return 0;
        } else {
            $info = curl_getinfo($ch);
            curl_close($ch);
            return $info["http_code"];
        }
    }

    public function move_uploaded_file($tmp_file, $destination) {

        if (!$tmp_file || !$destination) {
            return null;
        }

        if ($destination[0] != '/') {
            $destination = '/'.$destination;
        }

        $local_destination = $this->base_path.$destination;

        $res = move_uploaded_file($tmp_file, $local_destination);


        if (($res === false) && is_uploaded_file($tmp_file)) {
            $content = file_get_contents($tmp_file);
            if ($content !== false) {
                $res = $this->writefile($destination, $content, false);
            }
        } else {
            $res = 0;
        }

        return $res;
    }

    public function copy($old_filename, $new_filename, $make_dir = true) {

        if (!$old_filename || !$new_filename) {
            return null;
        }

        /*if ($old_filename[0] != '/') {
            $old_filename = '/'.$old_filename;
        }
        $local_old_filename = $this->base_path.$old_filename;
         * 
         */
        $local_old_filename = fx::path()->to_abs($old_filename);

        /*
        if ($new_filename[0] != '/') {
            $new_filename = '/'.$new_filename;
        }
        $local_new_filename = $this->base_path.$new_filename;
         * 
         */
        $local_new_filename = fx::path()->to_abs($new_filename);
        
        $local_parent_dir = dirname($local_new_filename);

        if (!is_dir($local_parent_dir)) {  // check whether there is a destination directory
            if ($make_dir) {
                $res = $this->mkdir($local_parent_dir);
            } else {
                return null;
            }
        }


        if (!is_dir($local_old_filename)) {  // copy 1 file
            return $this->_copy_file($local_old_filename, $local_new_filename);
        }

        // copy the directory
        $res = $this->mkdir($new_filename);

        if ($new_filename[strlen($new_filename) - 1] != '/') {
            $new_filename .= '/';
            $local_new_filename .= '/';
        }
        if ($old_filename[strlen($old_filename) - 1] != '/') {
            $old_filename .= '/';
            $local_old_filename .= '/';
        }

        $ls = $this->ls($old_filename);
        foreach ($ls as $v) {
            if ($v['dir']) {
                $res = $this->copy($old_filename.$v['name'], $new_filename.$v['name'], true);
            } else {
                $res = $this->_copy_file($v[path], $local_new_filename.$v['name']);
            }
        }

        return 0;
    }

    public function move($old_filename, $new_filename, $make_dir = true) {

        if (!$old_filename || !$new_filename) {
            return null;
        }

        $res = $this->copy($old_filename, $new_filename, $make_dir);
        if ($res !== 0) {
            return null;
        }

        $res = $this->rm($old_filename);

        return $res;
    }

    public function filesize($filename) {
        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }

        $local_filename = $this->base_path.$filename;

        return filesize($local_filename);
    }
    
    public static $format_sizes = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	
    public function readable_size($size, $round = 0) {
        if (!is_numeric($size) && file_exists($size) && is_file($size))  {
            $size = filesize($size);
        }
        $sizes = self::$format_sizes;
        $total = count($sizes);
        for ($i=0; $size > 1024 && $i < $total; $i++) {
            $size /= 1024;
        }
        $res = round($size,$round)." ".$sizes[$i];
        return $res;
    }

    public function file_exists($filename) {
        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }
        $local_filename = $this->base_path.$filename;
        return file_exists($local_filename);
    }

     public function is_writable($filename) {
        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }
        $local_filename = $this->base_path.$filename;
        return is_writable($local_filename);
    }

    public function file_include ( $filename, $vars = array() ) {
        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }

        $local_filename = $this->base_path.$filename;

        extract($vars);
        include($local_filename);
    }

    public function is_dir($filename) {
        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }

        $local_filename = $this->base_path.$filename;

        return is_dir($local_filename);
    }
    
    public function is_filename_allowed($filename) {
        $ext = fx::path()->file_extension($filename);
        if (!$ext) {
            return true;
        }
        return in_array($ext, $this->allowed_extensions);
    }
    
    public function get_extension_by_mime($mime) {
        $map = array(
            'image/bmp' => 'bmp',
            'image/gif' => 'gif',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/tiff' => 'tiff'
            // ... 
        );
        $mime = strtolower($mime);
        return isset($map[$mime]) ? $map[$mime] : '';
    }

    /**
     * Creates a temporary file (the file is automatically deleted in the destructor of the class)
     * @return mixed the file path relative to the site root
     */
    public function create_tmp_file() {
        do {  // generate a unique file name
            $local_filename = fx::config()->TMP_FOLDER.uniqid();
        } while (file_exists($local_filename));

        $h = fopen($local_filename, "w");
        if (!$h) {
            throw new fx_exception_files("Cannot create temporary file");
        }
        fclose($h);

        $count = 1;
        $filename = str_replace($this->base_path, '', $local_filename, $count);

        $this->tmp_files[] = $filename;
        return $filename;
    }

    /**
     * Creates a temporary directory (the file is automatically deleted in the destructor of the class)
     * @return mixed the directory path relative to the site root
     */
    public function create_tmp_dir() {
        do {  // generate a unique file name
            $local_filename = fx::config()->TMP_FOLDER.uniqid();
        } while (file_exists($local_filename));

        $res = mkdir($local_filename);
        if (!$res) {
            throw new fx_exception_files("Cannot create temporary directory");
        }

        $count = 1;
        $filename = str_replace($this->base_path, '', $local_filename, $count).'/';

        $this->tmp_files[] = $filename;
        return $filename;
    }

    /**
     * get remote file
     * @todo: we should refactor this code to make it safer
     * it's better to fetch and check headers before getting file contents 
     */
    public function save_remote_file($file, $dir = 'content', $name=null) {
        if (!preg_match("~^https?://~", $file)) {
            return;
        }
        $file_data = file_get_contents($file);
        if (!$file_data) {
            return;
        }
        $file_name = $name ? $name : fx::path()->file_name($file);
        $extension = fx::path()->file_extension($file_name);
        if (!$extension) {
            foreach ($http_response_header as $header) {
                if (preg_match("~^content-type: (.+)$~i", $header, $content_type)) {
                    $mime = $content_type[1];
                    $extension = $this->get_extension_by_mime($mime);
                    $file_name .= '.'.$extension;
                    break;
                }
            }
        }
        $put_file = $this->get_put_filename($dir, $file_name);
        $full_path = fx::path('files', $dir.'/'.$put_file);
        $this->writefile($full_path, $file_data);
        return $full_path;
    }

    public function save_file($file, $dir = '', $name=null) {
        // normal $_FILES
        if (is_array($file)) {
            $filename = $file['name'];
            $put_file = $this->get_put_filename($dir, $filename);
            $full_path = fx::path('files', $dir.'/'.$put_file);
            $this->mkdir(fx::path('files', $dir));
            $res = move_uploaded_file($file['tmp_name'], $full_path);
            if (!$res) {
                return;
            }
        } 
        // remote file
        else if (is_string($file) ) {
            $full_path = $this->save_remote_file($file, $dir, $name);
            if (!$full_path) {
                return;
            }
            $filename = fx::path()->file_name($full_path);
        }
        
        $http_path = fx::path()->to_http($full_path);
        
        return array(
            'path' => $http_path,
            'filename' => $filename,
            'fullpath' => $full_path,
            'size' => $this->readable_size(filesize($full_path))
        );
    }

    protected function get_put_filename($dir, $name) {
        $name = fx::util()->str_to_latin($name);
        $name = preg_replace("~[^a-z0-9_\.-]~i", '_', $name);
        $name = trim($name, "_");
        $name = preg_replace("~_+~", "_", $name);
        
        $path = fx::path('files', $dir.'/'.$name);
        
        $try = 0;
        while (fx::path()->exists($path)) {
            $c_name = preg_replace("~(\.[^\.]+)$~", "_".$try."\$1", $name);
            $try++;
            $path = fx::path('files', $dir.'/'.$c_name);
        }
        return fx::path()->file_name($path);
    }

    private function tar_check() {
        static $res;
        if ($res !== null) {
            return $res;
        }

        if ( !preg_match("/Windows/i", php_uname())) {  // it's not Windows
            $err_code = 127;
            @exec("tar --version", $output, $err_code);
            $res = $err_code ? false : true;
        } else {
            $res = false;
        }

        return $res;
    }

    public function get_full_path($filename) {
        if (!$filename) {
            return null;
        }

        if ($filename[0] != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        	$filename = '/'.$filename;
        }

        $filename = preg_replace("~^".preg_quote($this->base_path)."~", '', $filename);
		$filename = $this->base_path.DIRECTORY_SEPARATOR.preg_replace("~^".preg_quote(DIRECTORY_SEPARATOR)."~", '', $filename);
        return $filename;
    }

    public function mime_content_type($filename) {
        if (!$filename) {
            return null;
        }

        if ($filename[0] != '/') {
            $filename = '/'.$filename;
        }

        $local_filename = $this->base_path.$filename;

        if (!file_exists($local_filename) || is_dir($local_filename)) {
            return null;
        }

        // Try funds PHP
        if (extension_loaded('fileinfo')) {
            $finfo = new finfo;
            $fileinfo = $finfo->file($local_filename, FILEINFO_MIME_TYPE);
            return $fileinfo;
        }

        // Try through the shell
        $shell_filename = escapeshellarg($local_filename);
        @exec('file -b --mime-type '.$shell_filename.' 2>/dev/null', $output, $err_code);
        if (!$err_code && $output && $output[0]) {
            return $output[0];
        }

        // Themselves mutim =((
        return $this->my_mime_content_type($local_filename);
    }

    private function my_mime_content_type($local_filename) {
        preg_match("#\.([a-z0-9]{1,7})$#i", $local_filename, $fileSuffix);

        switch (strtolower($fileSuffix[1])) {
            case "js" :
                return "application/x-javascript";

            case "html" :
            case "htm" :
            case "php" :
                return "text/html";

            case "txt" :
                return "text/plain";

            case "mpeg" :
            case "mpg" :
            case "mpe" :
                return "video/mpeg";

            case "jpg" :
            case "jpeg" :
            case "jpe" :
                return "image/jpg";

            case "png" :
            case "gif" :
            case "bmp" :
            case "tiff" :
                return "image/".strtolower($fileSuffix[1]);

            case "css" :
                return "text/css";

            case "xml" :
                return "application/xml";

            case "doc" :
            case "docx" :
                return "application/msword";

            case "json" :
                return "application/json";

            case "xls" :
            case "xlt" :
            case "xlm" :
            case "xld" :
            case "xla" :
            case "xlc" :
            case "xlw" :
            case "xll" :
                return "application/vnd.ms-excel";

            case "ppt" :
            case "pps" :
                return "application/vnd.ms-powerpoint";

            case "rtf" :
                return "application/rtf";

            case "pdf" :
                return "application/pdf";

            case "mp3" :
                return "audio/mpeg3";

            case "wav" :
                return "audio/wav";

            case "aiff" :
            case "aif" :
                return "audio/aiff";

            case "avi" :
                return "video/msvideo";

            case "wmv" :
                return "video/x-ms-wmv";

            case "mov" :
                return "video/quicktime";

            case "zip" :
                return "application/zip";

            case "tar" :
                return "application/x-tar";

            case "swf" :
                return "application/x-shockwave-flash";

            default :
                return "application/".strtolower($fileSuffix[1]);
        }
    }

    public function is_image($filename) {
        return (strpos($this->mime_content_type($filename), 'image/') !== false);
    }

    public function get_file_error($error_num) {
        $text[UPLOAD_ERR_OK] = 'There is no error, the file uploaded with success.';
        $text[UPLOAD_ERR_INI_SIZE] = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
        $text[UPLOAD_ERR_FORM_SIZE] = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
        $text[UPLOAD_ERR_PARTIAL] = 'The uploaded file was only partially uploaded.';
        $text[UPLOAD_ERR_NO_FILE] = 'No file was uploaded.';
        $text[UPLOAD_ERR_NO_TMP_DIR] = 'Missing a temporary folder.';
        $text[UPLOAD_ERR_CANT_WRITE] = 'Failed to write file to disk.';
        $text[UPLOAD_ERR_EXTENSION] = 'A PHP extension stopped the file upload.';

        return $text[$error_num];
    }
    
    function unzip($file, $dir) {
        $dir = trim($dir, '/').'/';
        $dir = fx::config()->DOCUMENT_ROOT.
               fx::config()->HTTP_FILES_PATH.
               $dir;
        $this->zip_mkdir($dir);
        
        $zip_handle = zip_open($file);
        if (!is_resource($zip_handle)) {
            die("Problems while reading zip archive");
        }
        while ($zip_entry = zip_read($zip_handle)) {
            $zip_name = zip_entry_name($zip_entry);
            $zip_dir = dirname( zip_entry_name($zip_entry) );
            $zip_size = zip_entry_filesize($zip_entry);
            if (preg_match("~/$~", $zip_name)) {
                $new_dir_name = preg_replace("~/$~", '', $dir . $zip_name);
                $this->zip_mkdir($new_dir_name);
                chmod($new_dir_name, 0777);
            }
            else {
                zip_entry_open($zip_handle, $zip_entry, 'r');
                if (is_writable($dir . $zip_dir)) {
                    $fp = @fopen($dir . $zip_name, 'w');
                    if (is_resource($fp)) {
                        @fwrite($fp, zip_entry_read($zip_entry, $zip_size));
                        @fclose($fp);
                        chmod($dir.$zip_name, 0666);
                    }
                }
                zip_entry_close($zip_entry);
            }
        }
        zip_close($zip_handle);
        return true;
    }

    function zip_mkdir($dir, $chmod = 0755) {
        $slash = "/";
        if (substr(php_uname(), 0, 7) == "Windows") {
            $slash = "\\";
            $dir = str_replace("/", $slash, $dir);
        }

        $tree = explode($slash, $dir);

        $path = $slash;
        // win path begin from C:\
        if (substr(php_uname(), 0, 7) == "Windows") $path = "";

        foreach($tree as $row) {

            if($row === false) continue;

            if( !@is_dir($path . $row) ) {
                @mkdir( strval($path . $row), $chmod );
            }

            $path .= $row . $slash;
        }
    }

    public function send_download_file($path,$name,$size=null) {
        if (file_exists($path) and $file=fopen($path,"r")) {
            for ($i = 0; $i < ob_get_level(); $i++) {
                ob_end_clean();
            }
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=".urlencode($name).";");
            header("Content-Transfer-Encoding: binary");
            if ($size) {
                header("Content-Length: ".$size);
            }
            while (!feof($file)) {
                $content=fread($file ,1024*100);
                echo $content;
            }
            fx::env('complete_ok', true);
            exit(0);
        }
        return false;
    }
}

class fx_exception_files extends fx_exception {

}