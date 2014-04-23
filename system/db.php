<?php
class fx_db extends PDO {

    // information about the last error
    protected $last_error;
    // last result
    protected $last_result;
    protected $last_result_array;
    // the last query
    protected $last_query;
    // the request type ( insert, select, etc)
    protected $query_type;
    // the total number of requests
    protected $num_queries;

    public function __construct() {
        try {
            parent::__construct(fx::config()->DB_DSN, fx::config()->DB_USER, fx::config()->DB_PASSWORD);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        $this->prefix = fx::config()->DB_PREFIX ? fx::config()->DB_PREFIX.'_' : '';
    }

    public function escape($str) {
        $str = $this->quote($str);
        // hack!
        $str = trim($str, "'");
        return $str;
    }

    public function prepare($str) {
        return $this->escape($str);
    }

    protected static $q_time = 0;
    protected static $q_count = 0;
    
    public function prepare_query($statement) {
        return trim($this->replace_prefix($statement));
    }
    
    public function query($statement) {
        self::$q_count++;
    	$start_time = microtime(true);

        $statement = $this->prepare_query($statement);

        // determine the type of request
        preg_match("/^([a-z]+)\s+/i", $statement, $match);
        $this->query_type = strtolower($match[1]);

        $this->last_result = parent::query($statement);
        $this->num_queries++;
        $this->last_query = $statement;

        if (!$this->last_result) {
            $this->last_error = $this->errorInfo();
            echo "<div style='border: 2pt solid red; margin: 10px; padding:10px; font-size:13px; color:black;'><br/>\n";
            echo "Query: <b>" . $statement . "</b><br/>\n";
            echo "Error: <b>" . $this->last_error[2] . "</b><br/>\n";
            echo "</div>\n";
            fx::log($statement, debug_backtrace());
        }
        $end_time = microtime(true);
        $q_time = $end_time - $start_time;
        self::$q_time += $q_time;
        return $this->last_result;
        fx::log(
                '#'.self::$q_count, 
                'q_time: '.$q_time, 
                'q_total: '.self::$q_time,
                $statement
        );
        return $this->last_result;
    }

    public function get_row($query = null) {
        $res = array();

        if (!$query) {
            $res = $this->last_result_array;
        } else {
            if (($result = $this->query($query))) {
                $res = $result->fetch(PDO::FETCH_ASSOC);
                $this->last_result_array = $result->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return $res;
    }

    public function get_results($query = null, $result_type = PDO::FETCH_ASSOC) {
        $res = array();

        if (!$query) {
            $res = $this->last_result_array;
        } else {
            if (($result = $this->query($query))) {
                $res = $result->fetchAll($result_type);
                $this->last_result_array = $res; // ??? $result->fetchAll($result_type);
            }
        }
        return $res;
    }
    
    public function get_collection($query = null) {
        $res = $this->get_results($query, PDO::FETCH_ASSOC);
        return new fx_collection($res);
    }

    public function get_col ( $query = null, $col_num = 0 ) {
        $res = array();

        if (!$query) {
            $res = $this->last_result_array;
        } else {
            if (($result = $this->query($query))) {
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $res[] = $row[ $col_num];
                }
                $this->last_result_array = $result->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return $res;
    }

    public function get_var($query = null) {
        $res = array();
        
        if (!$query) {
            $res = $this->last_result_array;
        } else {
            if (($result = $this->query($query))) {
                $res = $result->fetch(PDO::FETCH_NUM);
                $res = $res[0];

                $this->last_result_array = $result->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return $res;
    }

    public function row_count() {
        return $this->last_result ? $this->last_result->rowCount() : 0;
    }

    public function insert_id() {
        return $this->lastInsertId();
    }

    public function is_error() {
        return!(bool) $this->last_result;
    }

    public function get_last_error() {
        return $this->last_error;
    }

    public function error_info() {
        return $this->errorInfo();
    }

    protected function replace_prefix($query) {
        if ( $this->prefix ) {
            $query = preg_replace('/{{(.*?)}}/', $this->prefix . '\1', $query);
        }
        return $query;
    }

    public function column_exists($table2check, $column) {
        $sql = "SHOW COLUMNS FROM {{".$table2check."}}";
        foreach ($this->get_col($sql) as $column_name) {
            if ($column_name == $column) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * get the sql code of the last executed query
     * @return string
     */
    public function get_last_query() {
        return $this->last_query;
    }

}