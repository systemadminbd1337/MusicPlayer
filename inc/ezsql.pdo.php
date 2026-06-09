<?php
// ======================================================================
//  ezSQL PDO Driver — PHP 8.2+ Compatible, Fully Backward Safe
//  Author: Justin Vincent (jv@jvmultimedia.com)
//  Maintainer Patch: Systemadminbd (Dynamic Property Fix for PHP 8.2)
// ======================================================================

//  Error strings
global $ezsql_pdo_str;
$ezsql_pdo_str = array(
    1 => 'Require $dsn and $user and $password to create a connection'
);

//  Dependencies
if (!class_exists('PDO')) die('<b>Fatal Error:</b> ezSQL_pdo requires PDO Lib to be compiled and or linked in to the PHP engine');
if (!class_exists('ezSQLcore')) die('<b>Fatal Error:</b> ezSQL_pdo requires ezSQLcore (ez_sql_core.php) to be included/loaded before it can be used');

// ======================================================================
//  Main Class
// ======================================================================
class ezSQL_pdo extends ezSQLcore
{
    // ------------------------------------------------------------
    // ✅ Officially Declared Public Properties (PHP 8.2 Fix)
    // ------------------------------------------------------------
    public $dbh = null;
    public $dsn = '';
    public $user = '';
    public $password = '';
    public $rows_affected = 0;
    public $insert_id = 0;
    public $num_rows = 0;
    public $last_query = '';
    public $last_result = array();
    public $col_info = array();
    public $func_call = '';
    public $from_disk_cache = false;
    public $trace = false;
    public $trace_log = array();
    public $use_trace_log = false;
    public $conn_queries = 0;

    // ------------------------------------------------------------
    //  Constructor
    // ------------------------------------------------------------
    function __construct($dsn = '', $user = '', $password = '', $ssl = array())
    {
        ini_set('track_errors', 1);
        if ($dsn && $user) {
            $this->connect($dsn, $user, $password, $ssl);
        }
    }

    // ------------------------------------------------------------
    //  Connect to DB
    // ------------------------------------------------------------
    function connect($dsn = '', $user = '', $password = '', $ssl = array())
    {
        global $ezsql_pdo_str;
        $return_val = false;

        if (!$dsn || !$user) {
            $this->register_error($ezsql_pdo_str[1] . ' in ' . __FILE__ . ' on line ' . __LINE__);
            if ($this->show_errors) trigger_error($ezsql_pdo_str[1], E_USER_WARNING);
            return false;
        }

        try {
            if (!empty($ssl)) {
                $this->dbh = new PDO($dsn, $user, $password, $ssl);
            } else {
                @$this->dbh = new PDO($dsn, $user, $password);
            }
            $this->conn_queries = 0;
            $return_val = true;
        } catch (PDOException $e) {
            echo 'Database connection error';
            exit();
            $this->register_error($e->getMessage());
            if ($this->show_errors) trigger_error($e->getMessage(), E_USER_WARNING);
        }

        return $return_val;
    }

    // ------------------------------------------------------------
    //  Quick Connect
    // ------------------------------------------------------------
    function quick_connect($dsn = '', $user = '', $password = '', $ssl = array())
    {
        return $this->connect($dsn, $user, $password, $ssl);
    }

    // ------------------------------------------------------------
    //  Select (for compatibility)
    // ------------------------------------------------------------
    function select($dsn = '', $user = '', $password = '', $ssl = array())
    {
        return $this->connect($dsn, $user, $password, $ssl);
    }

    // ------------------------------------------------------------
    //  Escape String
    // ------------------------------------------------------------
    function escape($str)
    {
        switch (gettype($str)) {
            case 'string':
                $str = addslashes(stripslashes($str));
                break;
            case 'boolean':
                $str = ($str === FALSE) ? 0 : 1;
                break;
            default:
                $str = ($str === NULL) ? 'NULL' : $str;
                break;
        }
        return $str;
    }

    // ------------------------------------------------------------
    //  System Date Function
    // ------------------------------------------------------------
    function sysdate()
    {
        return "NOW()";
    }

    // ------------------------------------------------------------
    //  Catch PDO Errors
    // ------------------------------------------------------------
    function catch_error()
    {
        $error_str = 'No error info';
        $err_array = $this->dbh->errorInfo();

        // Ignore harmless bind/column index errors
        if (isset($err_array[1]) && $err_array[1] != 25) {
            $error_str = '';
            foreach ($err_array as $entry) {
                $error_str .= $entry . ', ';
            }

            $this->register_error($error_str);
            if ($this->show_errors)
                trigger_error($error_str . ' ' . $this->last_query, E_USER_WARNING);

            return true;
        }
        return false;
    }

    // ------------------------------------------------------------
    //  Main Query Executor
    // ------------------------------------------------------------
    function query($query)
    {
        $query = str_replace("/[\n\r]/", '', trim($query));
        $return_val = 0;
        $this->flush();
        $this->func_call = "\$db->query(\"$query\")";
        $this->last_query = $query;
        $this->count(true, true);
        $this->timer_start($this->num_queries);

        // Cached?
        if ($cache = $this->get_cache($query)) {
            $this->timer_update_global($this->num_queries);
            if ($this->use_trace_log) $this->trace_log[] = $this->debug(false);
            return $cache;
        }

        // Ensure connection
        if (!isset($this->dbh) || !$this->dbh) {
            $this->connect($this->dsn, $this->user, $this->password);
            if (!isset($this->dbh) || !$this->dbh)
                return false;
        }

        // Detect write vs read query
        if (preg_match("/^(insert|delete|update|replace|drop|create)\s+/i", $query)) {
            $this->rows_affected = $this->dbh->exec($query);
            if ($this->catch_error()) return false;
            $is_insert = true;
            if (preg_match("/^(insert|replace)\s+/i", $query)) {
                $this->insert_id = @$this->dbh->lastInsertId();
            }
            $return_val = $this->rows_affected;
        } else {
            $sth = $this->dbh->query($query);
            if ($this->catch_error()) return false;

            $is_insert = false;
            $col_count = $sth->columnCount();

            for ($i = 0; $i < $col_count; $i++) {
                $this->col_info[$i] = new stdClass();
                if ($meta = $sth->getColumnMeta($i)) {
                    $this->col_info[$i]->name = $meta['name'];
                    $this->col_info[$i]->type = !empty($meta['native_type']) ? $meta['native_type'] : 'undefined';
                    $this->col_info[$i]->max_length = '';
                } else {
                    $this->col_info[$i]->name = 'undefined';
                    $this->col_info[$i]->type = 'undefined';
                    $this->col_info[$i]->max_length = '';
                }
            }

            $num_rows = 0;
            while ($row = @$sth->fetch(PDO::FETCH_ASSOC)) {
                $this->last_result[$num_rows] = (object) $row;
                $num_rows++;
            }

            $this->num_rows = $num_rows;
            $return_val = $this->num_rows;
        }

        // Store cache, update timers, traces
        $this->store_cache($query, isset($is_insert) && $is_insert);
        if ($this->trace || $this->debug_all) $this->debug();
        $this->timer_update_global($this->num_queries);
        if ($this->use_trace_log) $this->trace_log[] = $this->debug(false);

        return $return_val;
    }
}
