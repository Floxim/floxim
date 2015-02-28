<?php

namespace Floxim\Floxim\System;

class Util
{

    public function isEven($input)
    {
        return (bool)(round($input / 2) == $input / 2);
    }

    /*
     * @todo curl
     */

    public function httpRequest($url, $params = '')
    {
        if (is_array($params)) {
            $params = http_build_query($params, null, '&');
        }
        if ($params) {
            $url .= '?' . $params;
        }
        return @file_get_contents($url);
    }

    /**
     * Retrieving title, and meta-data pages
     * @see http://php.net/manual/en/function.get-meta-tags.php
     * @param string the url of the page
     * @return array
     */
    public function getMetaTags($url)
    {
        $result = array();
        $contents = @file_get_contents($url);
        if (!$contents) {
            return false;
        }

        // title
        preg_match('/<title>([^>]*)<\/title>/si', $contents, $match);
        if (isset($match) && is_array($match) && count($match) > 0) {
            $result['title'] = strip_tags($match[1]);
        }

        // h1
        preg_match('/<h1(.+?)>(.+?)<\/h1>/si', $contents, $match);
        if (count($match) > 0) {
            $result['h1'] = strip_tags($match[2]);
        }

        preg_match_all('/<[\s]*meta[\s]*name=["\']?' . '([^>\'"]*)["\']?[\s]*' . 'content=["\']?([^>"\']*)["\']?[\s]*[\/]?[\s]*>/si',
            $contents, $match);

        if (isset($match) && is_array($match) && count($match) == 3) {
            $originals = $match[0];
            $names = $match[1];
            $values = $match[2];

            if (count($originals) == count($names) && count($names) == count($values)) {
                for ($i = 0, $limiti = count($names); $i < $limiti; $i++) {
                    $result[strtolower($names[$i])] = $values[$i];
                }
            }
        }

        return $result;
    }

    public function checkGzip()
    {
        // check "ob_gzhandler" existion
        $gzip_exist = false;
        if (ob_list_handlers()) {
            $gzip_exist = in_array("ob_gzhandler", ob_list_handlers());
        }
        // if compression not enabled yet
        if (!$gzip_exist) {
            // get HTTP_ACCEPT_ENCODING string
            $encode_string = explode(",", $_SERVER['HTTP_ACCEPT_ENCODING']);
            $result = false;
            foreach ($encode_string as $value) {
                // parse value
                $value = trim($value);
                if ($value === "gzip" || $value === "x-gzip") {
                    $result = $value;
                    break;
                }
            }
        }

        return $result;
    }

    public function conv($from, $to, $text)
    {
        return iconv($from, $to, $text);
    }

    /**
     * Generates UUIDs
     */
    public function genUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Validates your e-mail
     * @param string $email
     */
    public function validateEmail($email)
    {
        $res = preg_match("#^[-a-z0-9!\\#\$%&'*+/=?^_`{|}~]+(?:\\.[-a-z0-9!\\#\$%&'*+/=?^_`{|}~]+)*@(?:[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])?\\.)*(?:[a-z]{2,})\$#",
            $email);
        return $res ? true : false;
    }

    /**
     * Method responsive attributes of the tag in the array
     * @param string parameters in a line, for example, 'keyword="center" repeat="yes"'
     * @return array an array with parameters, for example, array('keyword'=>'center', 'repeat'=>'yes')
     */
    public function parseAttr($attr = "")
    {
        if (!$attr) {
            return array();
        }
        //$re = "/([a-z0-9_-]+)\s*=\s*(['\"])([^\\2]+)\\2\s+/iu";
        $re = "/([a-z0-9_-]+)\s*=\s*\"([^\"]+)\"\s+/iu";
        if (preg_match_all($re, trim($attr) . ' ', $match, PREG_SET_ORDER)) {
            foreach ($match as $param) {
                $result[$param[1]] = $param[2];
            }
        }

        return $result;
    }

    /**
     * Do previews long text
     * @param type $text , long text, previews and which do
     * @param type $max_length how many maximum Simonov in the thumbnail
     */
    public function getTextPreview($text, $max_length = 150)
    {
        if (strlen($text) <= $max_length) {
            return $text;
        }

        $text = substr($text, 0, $max_length - strlen(strrchr(substr($text, 0, $max_length), ' '))) . "&hellip;";
    }

    public function isMysqlKeyword($name)
    {
        return in_array(strtolower($name), preg_split("/\s+/", "accessible add all alter analyze and as asc asensitive
                  before between bigint binary blob both by call cascade
                  case change char character check collate column condition connection
                  constraint continue convert create cross current_date
                  current_time current_timestamp current_user
                  cursor database databases day_hour day_microsecond day_minute
                  day_second dec decimal declare default delayed delete desc describe
                  deterministic distinct distinctrow div double drop dual each else
                  elseif enclosed escaped exists exit explain false fetch float
                  float4 float8 for force foreign from fulltext goto grant
                  group having high_priority hour_microsecond hour_minute hour_second
                  if ignore in index infile inner inout insensitive insert
                  int int1 int2 int3 int4 int8 integer interval into is iterate join
                  key keys kill label leading leave left like limit linear lines load
                  localtime localtimestamp lock long longblob longtext
                  loop low_priority match mediumblob mediumint mediumtext
                  middleint minute_microsecond minute_second mod modifies natural
                  not no_write_to_binlog null numeric on optimize
                  option optionally or order out outer outfile precision primary
                  procedure purge range read reads read_only read_write real references
                  regexp release rename repeat replace require restrict return revoke
                  right rlike schema schemas second_microsecond select sensitive separator set
                  show smallint spatial specific sql sqlexception sqlstate sqlwarning sql_big_result
                  sql_calc_found_rows sql_small_result ssl starting straight_join table
                  terminated then tinyblob tinyint tinytext to trailing trigger true
                  undo union unique unlock unsigned update upgrade usage use
                  using utc_date utc_time utc_timestamp values varbinary
                  varchar varcharacter varying when where while with write x509
                  xor year_month zerofill"));
    }

    public function strToLatin($str)
    {
        $tr = array(
            "А"  => "A",
            "а"  => "a",
            "Б"  => "B",
            "б"  => "b",
            "В"  => "V",
            "в"  => "v",
            "Г"  => "G",
            "г"  => "g",
            "Д"  => "D",
            "д"  => "d",
            "Е"  => "E",
            "е"  => "e",
            "Ё"  => "E",
            "ё"  => "e",
            "Ж"  => "Zh",
            "ж"  => "zh",
            "З"  => "Z",
            "з"  => "z",
            "И"  => "I",
            "и"  => "i",
            "Й"  => "Y",
            "й"  => "y",
            "КС" => "X",
            "кс" => "x",
            "К"  => "K",
            "к"  => "k",
            "Л"  => "L",
            "л"  => "l",
            "М"  => "M",
            "м"  => "m",
            "Н"  => "N",
            "н"  => "n",
            "О"  => "O",
            "о"  => "o",
            "П"  => "P",
            "п"  => "p",
            "Р"  => "R",
            "р"  => "r",
            "С"  => "S",
            "с"  => "s",
            "Т"  => "T",
            "т"  => "t",
            "У"  => "U",
            "у"  => "u",
            "Ф"  => "F",
            "ф"  => "f",
            "Х"  => "H",
            "х"  => "h",
            "Ц"  => "Ts",
            "ц"  => "ts",
            "Ч"  => "Ch",
            "ч"  => "ch",
            "Ш"  => "Sh",
            "ш"  => "sh",
            "Щ"  => "Sch",
            "щ"  => "sch",
            "Ы"  => "Y",
            "ы"  => "y",
            "Ь"  => "'",
            "ь"  => "'",
            "Э"  => "E",
            "э"  => "e",
            "Ъ"  => "'",
            "ъ"  => "'",
            "Ю"  => "Yu",
            "ю"  => "yu",
            "Я"  => "Ya",
            "я"  => "ya"
        );

        $tr_text = strtr($str, $tr);

        return $tr_text;
    }

    public function strToKeyword($str)
    {
        $str = $this->strToLatin($str);
        $str = strtolower($str);
        $str = preg_replace("~[^a-z0-9_-]+~", '_', $str);
        return $str;
    }

    public function camelToUnderscore($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }

    public function underscoreToCamel($string, $first_upper = true)
    {
        $t_string = trim($string, '_');
        $parts = explode('_', $t_string);
        $camelized = '';
        foreach ($parts as $part_num => $part) {
            if ($part_num === 0 && $first_upper === false) {
                $camelized .= $part;
            } elseif ($part === '') {
                $camelized .= '_';
            } else {
                $camelized .= ucfirst($part);
            }
        }
        return $camelized;
    }
    
    /**
     * 
     * @param type $type data | meta
     */
    public static function dumperGetTables($type) 
    {
        $meta = array(
            'component',
            'datatype',
            'field',
            'floxim_user_user',
            'lang',
            'lang_string',
            'layout',
            'module',
            'option',
            'patch',
            'patch_migration',
            'widget'
        );
        
        if ($type === 'meta') {
            return $meta;
        }
        
        $all = array_keys(fx::schema());
        
        // do not export content table, it needs special treatment
        $meta []= 'floxim_main_content'; 
        return array_diff($all, $meta);
    }


    /**
     * Create SQL dump file for empty system with no site-related data
     * @param type $target_file
     */
    public function dumpMeta($target_file = null) 
    {
        if (!$target_file) {
            $target_file = fx::path('/install/floxim_meta.sql');
        }
        
        fx::db()->dump(array(
            'tables' => self::dumperGetTables('data'),
            'data' => false,
            'file' => $target_file
        ));
        
        fx::db()->dump(array(
            'tables' => self::dumperGetTables('meta'),
            'file' => $target_file,
            'add' => true
        ));
        
        fx::db()->dump(array(
            'tables' => array('floxim_main_content'),
            'where' => "type = 'floxim.user.user'",
            'file' => $target_file,
            'add' => true
        ));
    }
    
    /**
     * Create dump file to import site-related data (from all sites)
     */
    public function dumpData($target_file = null)
    {
        if (!$target_file) {
            $target_file = fx::path('/install/floxim_data.sql');
        }
        
        $tables = self::dumperGetTables('data');
        
        fx::db()->dump(array(
            'tables' => array_diff($tables, array('session')),
            'data' => true,
            'schema' => false,
            'file' => $target_file
        ));
        
        fx::db()->dump(array(
            'tables' => array('floxim_main_content'),
            'where' => "type != 'floxim.user.user'",
            'file' => $target_file,
            'schema' => false,
            'add' => true
        ));
    }
    
    /**
     * 
     */
    public function dumpSiteData($site_id = null, $target_file = null)
    {
        if (is_null($site_id)) {
            $site_id = fx::env('site_id');
        }
        
        if (is_null($target_file)) {
            $dir = '@files/export/site_'.$site_id;
            fx::files()->mkdir($dir);
            $target_file = fx::path()->abs($dir.'/data.sql');
        }
        
        // export the site
        fx::db()->dump(array(
            'tables' => array('site'),
            'where' => 'id = '.$site_id,
            'schema' => false,
            'file' => $target_file
        ));
        
        // export infoblocks
        fx::db()->dump(array(
            'tables' => array('infoblock'),
            'where' => 'site_id = '.$site_id,
            'schema' => false,
            'file' => $target_file,
            'add' => true
        ));
        
        // export URL aliases
        fx::db()->dump(array(
            'tables' => array('url_alias'),
            'where' => 'site_id = '.$site_id,
            'schema' => false,
            'file' => $target_file,
            'add' => true
        ));
        
        // export infoblock_visual
        $infoblock_ids = fx::data('infoblock')->where('site_id', $site_id)->all()->getValues('id');
        
        fx::db()->dump(array(
            'tables' => array('infoblock_visual'),
            'where' => 'infoblock_id IN ('.join(", ", $infoblock_ids).')',
            'schema' => false,
            'file' => $target_file,
            'add' => true
        ));
        
        
        // export main content table
        fx::db()->dump(array(
            'tables' => array('floxim_main_content'),
            'where' => 'site_id  = '.$site_id,
            'schema' => false,
            'file' => $target_file,
            'add' => true
        ));
        
        // get existing content items
        $items = fx::db()->getResults('select id, type from {{floxim_main_content}} where site_id = '.$site_id);
        $items = fx::collection($items)->group('type');

        $tables = array();

        foreach ($items as $com_keyword => $data) {
            $com = fx::component($com_keyword);
            $com_tables = $com->getAllTables();
            foreach ($com_tables as $t) {
                if ($t === 'floxim_main_content') {
                    continue;
                }
                if (!isset($tables[$t])) {
                    $tables[$t] = array();
                }
                $tables[$t] = array_merge($tables[$t], $data->getValues('id'));
            }
        }
        
        foreach ($tables as $t => $item_ids) {
            // export content table
            fx::db()->dump(array(
                'tables' => array($t),
                'where' => 'id IN ('.join(',', $item_ids).')',
                'schema' => false,
                'file' => $target_file,
                'add' => true
            ));
        }
    }
}