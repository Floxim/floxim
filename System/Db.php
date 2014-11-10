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
            throw new \Exception(
                "Query: " . $statement . "\n" .
                "Error: " . $this->last_error[2]
            );
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

}