<?php

namespace Floxim\Floxim\System;

class Db extends \PDO
{

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

    public function __construct()
    {
        try {
            parent::__construct(fx::config('db.dsn'), fx::config('db.user'), fx::config('db.password'));
            $prefix = fx::config('db.prefix');
            $this->prefix = $prefix ? $prefix . '_'  : '';
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function escape($str)
    {
        return addslashes($str);
        //$str = $this->quote($str);
        // hack! what for???
        // $str = trim($str, "'");
        return $str;
    }

    public function prepare($str)
    {
        return $this->escape($str);
    }

    protected static $q_time = 0;
    protected static $q_count = 0;

    public function prepareQuery($statement)
    {
        if (is_array($statement)) {
            $query = call_user_func_array('sprintf', $statement);
        } else {
            $query = $statement;
        }
        return trim($this->replacePrefix($query));
    }
    
    /**
     * Dummy method for quick and dirty updates not using API
     * @param string $table Table to update (with no prefix)
     * @param array $values Array of fields to update (key - field, value - new value)
     * @param mixed $where id or array of field-value pairs (will be joined with "and") or raw condition string
     */
    public function update($table, $values, $where)
    {
        $q = 'update {{'.$table.'}} set ';
        $parts = array();
        foreach ($values as $field => $value) {
            $parts []= '`'.$field.'` = "'.$this->escape($value).'" ';
        }
        $q .= join(", ", $parts);
        if (is_numeric($where)) {
            $where = array('id' => $where);
        }
        if (is_array($where) && count($where) > 0) {
            $q .= ' where ';
            $where_parts = array();
            foreach ($where as $field => $value) {
                $where_parts []= '`'.$field.'` = "'.$this->escape($value).'"';
            }
            $q .= join(" AND ", $where_parts);
        } elseif (is_string($where)) {
            $q .= ' where '.$where;
        }
        $this->query($q);
    }

    public function query($statement)
    {
        self::$q_count++;
        $start_time = microtime(true);

        $statement = $this->prepareQuery($statement);

        // determine the type of request
        preg_match("/^([a-z]+)\s+/i", $statement, $match);
        $this->query_type = strtolower($match[1]);
        $this->last_result = parent::query($statement);
        $this->num_queries++;
        $this->last_query = $statement;

        if (!$this->last_result) {

            $this->last_error = $this->errorInfo();
            fx::log(
                'sql error', 
                $this->last_error,
                $statement, 
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
            );
            throw new \Exception(
                "Query: " . $statement . "\n" .
                "Error: " . $this->last_error[2]
            );
        }
        $trace_rex = fx::config('dev.log_sql_backtrace');
        if ($trace_rex && preg_match($trace_rex, $statement)) {
            fx::log($trace_rex, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        }
        if (!fx::config('dev.log_sql')) {
            return $this->last_result;
        }
        $end_time = microtime(true);
        $q_time = $end_time - $start_time;
        self::$q_time += $q_time;
        fx::log(
            '#' . self::$q_count,
            'q_time: ' . $q_time,
            'q_total: ' . self::$q_time,
            $statement//,
        //debug_backtrace()
        );
        return $this->last_result;
    }
    
    public function getSchema() {
        $db = $this;
        return fx::cache('meta')->remember(
            'schema', 
            function() use ($db) {
                $tables = $db->getCol('show tables');
                $res = array();
                foreach ($tables as $t) {
                    $table_name = preg_replace("~^".$db->prefix."~", '', $t);
                    $res[$table_name] = $db->getIndexedResults('show columns from `'.$t.'`', 'Field');
                }
                return $res;
            },
            60*60
        );
    }

    public function getRow($query = null)
    {
        $res = array();

        if (!$query) {
            $res = $this->last_result_array;
        } else {
            if (($result = $this->query($query))) {
                $res = $result->fetch(\PDO::FETCH_ASSOC);
                $this->last_result_array = $result->fetchAll(\PDO::FETCH_ASSOC);
            }
        }

        return $res;
    }

    public function getResults($query = null, $result_type = \PDO::FETCH_ASSOC)
    {
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

    public function getIndexedResults($query, $index = 'id')
    {
        $res = array();
        if (($result = $this->query($query))) {
            while (($row = $result->fetch(\PDO::FETCH_ASSOC))) {
                $res[$row[$index]] = $row;
            }
        }
        return $res;
    }

    public function getCollection($query = null)
    {
        $res = $this->getResults($query, \PDO::FETCH_ASSOC);
        return new Collection($res);
    }

    public function getCol($query = null, $col_num = 0)
    {
        $res = array();

        if (!$query) {
            $res = $this->last_result_array;
        } else {
            if (($result = $this->query($query))) {
                while ($row = $result->fetch(\PDO::FETCH_NUM)) {
                    $res[] = $row[$col_num];
                }
                $this->last_result_array = $result->fetchAll(\PDO::FETCH_ASSOC);
            }
        }

        return $res;
    }

    public function getVar($query = null)
    {
        $res = array();

        if (!$query) {
            $res = $this->last_result_array;
        } elseif (($result = $this->query($query))) {
            $res = $result->fetch(\PDO::FETCH_NUM);
            if (!is_array($res)) {
                return false;
            }
            $res = $res[0];
            $this->last_result_array = $result->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            return false;
        }
        return $res;
    }

    public function rowCount()
    {
        return $this->last_result ? $this->last_result->rowCount() : 0;
    }

    public function insertId()
    {
        return $this->lastInsertId();
    }

    public function isError()
    {
        return !(bool)$this->last_result;
    }

    public function getLastError()
    {
        return $this->last_error;
    }

    protected function replacePrefix($query)
    {
        if ($this->prefix) {
            $query = preg_replace('/{{(.*?)}}/', $this->prefix . '\1', $query);
        }
        return $query;
    }

    public function columnExists($table2check, $column)
    {
        $sql = "SHOW COLUMNS FROM {{" . $table2check . "}}";
        foreach ($this->getCol($sql) as $column_name) {
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
    public function getLastQuery()
    {
        return $this->last_query;
    }
    
    public function dump($params) {
        
        $params = array_merge(array(
            'data' => true,     // export data or not
            'schema' => true,   // generate CREATE TABLE or not
            'add' => false,     // add data to existing file or overwrite it
            'where' => false,   // condition,
            'tables' => array()
        ), $params);
        
        $dump_path = fx::config('dev.mysqldump_path');
        if (!$dump_path) {
            return;
        }
        $command = $dump_path.' -u'.fx::config('db.user').' -p'.fx::config('db.password').' --host='.fx::config('db.host');
        
        $command .= ' '.fx::config('db.name');
        
        if (!$params['schema']) {
            $command .= ' --no-create-info';
        }
        
        if (!$params['data']) {
            $command .= ' --no-data';
        }
        
        $command .= ' --skip-comments';
        
        if ($params['where']) {
            $command .= ' --where="'.$params['where'].'"';
        }
        
        foreach ($params['tables'] as $t) {
            $command .= ' '.$this->replacePrefix('{{'.$t.'}}');
        }
        
        $command .= ($params['add'] ? ' >> ' : ' > ').$params['file'];
        exec($command);
    }
}