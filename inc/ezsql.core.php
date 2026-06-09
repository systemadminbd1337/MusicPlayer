<?php
/**********************************************************************
*  Author: Justin Vincent (jv@vip.ie)
*  Web...: http://justinvincent.com
*  Name..: ezSQL
*  Desc..: ezSQL Core module - database abstraction library to make
*          it very easy to deal with databases. ezSQLcore can not be used by
*          itself (it is designed for use by database specific modules).
**********************************************************************/

define('EZSQL_VERSION', '2.17');
define('OBJECT', 'OBJECT');
define('ARRAY_A', 'ARRAY_A');
define('ARRAY_N', 'ARRAY_N');

/**********************************************************************
*  Core class containing common functions to manipulate query results
**********************************************************************/
class ezSQLcore
{
    var $trace = false;
    var $debug_all = false;
    var $debug_called = false;
    var $vardump_called = false;
    var $show_errors = true;
    var $num_queries = 0;
    var $conn_queries = 0;
    var $last_query = null;
    var $last_error = null;
    var $col_info = null;
    var $captured_errors = array();
    var $cache_dir = false;
    var $cache_queries = false;
    var $cache_inserts = false;
    var $use_disk_cache = false;
    var $cache_timeout = 24;
    var $timers = array();
    var $total_query_time = 0;
    var $db_connect_time = 0;
    var $trace_log = array();
    var $use_trace_log = false;
    var $sql_log_file = false;
    var $do_profile = false;
    var $profile_times = array();
    var $debug_echo_is_on = true;

    function __construct() {}

    function get_host_port($host, $default = false)
    {
        $port = $default;
        if (false !== strpos($host, ':')) {
            list($host, $port) = explode(':', $host);
            $port = (int)$port;
        }
        return array($host, $port);
    }

    function register_error($err_str)
    {
        $this->last_error = $err_str;
        $this->captured_errors[] = array('error_str' => $err_str, 'query' => $this->last_query);
    }

    function show_errors() { $this->show_errors = true; }
    function hide_errors() { $this->show_errors = false; }

    function flush()
    {
        $this->last_result = null;
        $this->col_info = null;
        $this->last_query = null;
        $this->from_disk_cache = false;
    }

    /**********************************************************************
    *  Safe get_var
    **********************************************************************/
    function get_var($query = null, $x = 0, $y = 0)
    {
        $this->func_call = "\$db->get_var(\"$query\", $x, $y)";
        if ($query) { $this->query($query); }

        // ✅ Safe guard fix
        if (!isset($this->last_result) || !is_array($this->last_result) || empty($this->last_result) ||
            !isset($this->last_result[$y]) || !is_object($this->last_result[$y])) {
            return null;
        }

        $values = array_values(get_object_vars($this->last_result[$y]));
        return (isset($values[$x]) && $values[$x] !== '') ? $values[$x] : null;
    }

    /**********************************************************************
    *  Safe get_row
    **********************************************************************/
    function get_row($query = null, $output = OBJECT, $y = 0)
    {
        $this->func_call = "\$db->get_row(\"$query\", $output, $y)";
        if ($query) { $this->query($query); }

        if (!isset($this->last_result) || !is_array($this->last_result) ||
            empty($this->last_result) || !isset($this->last_result[$y])) {
            return ($output === ARRAY_A || $output === ARRAY_N) ? array() : null;
        }

        if ($output == OBJECT) {
            return $this->last_result[$y] ?? null;
        } elseif ($output == ARRAY_A) {
            return get_object_vars($this->last_result[$y]);
        } elseif ($output == ARRAY_N) {
            return array_values(get_object_vars($this->last_result[$y]));
        } else {
            if ($this->show_errors) {
                trigger_error(" \$db->get_row(string query, output type, int offset) -- Output type must be OBJECT, ARRAY_A, or ARRAY_N", E_USER_WARNING);
            }
        }
    }

    /**********************************************************************
    *  Safe get_col
    **********************************************************************/
    function get_col($query = null, $x = 0)
    {
        $new_array = array();
        if ($query) { $this->query($query); }

        if (!isset($this->last_result) || !is_array($this->last_result) || empty($this->last_result)) {
            return $new_array;
        }

        $j = count($this->last_result);
        for ($i = 0; $i < $j; $i++) {
            $new_array[$i] = $this->get_var(null, $x, $i);
        }
        return $new_array;
    }

    /**********************************************************************
    *  Safe get_results
    **********************************************************************/
    function get_results($query = null, $output = OBJECT)
    {
        $this->func_call = "\$db->get_results(\"$query\", $output)";
        if ($query) { $this->query($query); }

        if (!isset($this->last_result) || !is_array($this->last_result)) {
            $this->last_result = array();
        }

        if ($output == OBJECT) {
            return $this->last_result;
        } elseif ($output == ARRAY_A || $output == ARRAY_N) {
            $new_array = array();
            if ($this->last_result) {
                $i = 0;
                foreach ($this->last_result as $row) {
                    $new_array[$i] = get_object_vars($row);
                    if ($output == ARRAY_N) {
                        $new_array[$i] = array_values($new_array[$i]);
                    }
                    $i++;
                }
            }
            return $new_array;
        }
        return array();
    }

    function get_col_info($info_type = "name", $col_offset = -1)
    {
        if ($this->col_info) {
            if ($col_offset == -1) {
                $i = 0;
                foreach ($this->col_info as $col) {
                    $new_array[$i] = $col->{$info_type};
                    $i++;
                }
                return $new_array;
            } else {
                return $this->col_info[$col_offset]->{$info_type};
            }
        }
        return null;
    }

    /**********************************************************************
    * Cache helpers
    **********************************************************************/
    function store_cache($query, $is_insert)
    {
        $cache_file = $this->cache_dir . '/' . md5($query);
        if ($this->use_disk_cache && (($this->cache_queries && !$is_insert) || ($this->cache_inserts && $is_insert))) {
            if (!is_dir($this->cache_dir)) {
                $this->register_error("Could not open cache dir: $this->cache_dir");
                if ($this->show_errors) trigger_error("Could not open cache dir: $this->cache_dir", E_USER_WARNING);
            } else {
                $result_cache = array(
                    'col_info' => $this->col_info,
                    'last_result' => $this->last_result,
                    'num_rows' => $this->num_rows,
                    'return_value' => $this->num_rows,
                );
                file_put_contents($cache_file, serialize($result_cache));
                if (file_exists($cache_file . ".updating")) unlink($cache_file . ".updating");
            }
        }
    }

    function get_cache($query)
    {
        $cache_file = $this->cache_dir . '/' . md5($query);
        if ($this->use_disk_cache && file_exists($cache_file)) {
            if ((time() - filemtime($cache_file)) > ($this->cache_timeout * 3600) &&
                !(file_exists($cache_file . ".updating") && (time() - filemtime($cache_file . ".updating") < 60))) {
                touch($cache_file . ".updating");
            } else {
                $result_cache = unserialize(file_get_contents($cache_file));
                $this->col_info = $result_cache['col_info'];
                $this->last_result = $result_cache['last_result'];
                $this->num_rows = $result_cache['num_rows'];
                $this->from_disk_cache = true;
                $this->trace || $this->debug_all ? $this->debug() : null;
                return $result_cache['return_value'];
            }
        }
    }

    /**********************************************************************
    * Timer and profiler utilities
    **********************************************************************/
    function timer_get_cur() { list($u, $s) = explode(" ", microtime()); return ((float)$u + (float)$s); }
    function timer_start($n) { $this->timers[$n] = $this->timer_get_cur(); }
    function timer_elapsed($n) { return round($this->timer_get_cur() - $this->timers[$n], 2); }
    function timer_update_global($n)
    {
        if ($this->do_profile) {
            $this->profile_times[] = array('query' => $this->last_query, 'time' => $this->timer_elapsed($n));
        }
        $this->total_query_time += $this->timer_elapsed($n);
    }

    /**********************************************************************
    * get_set helper for building SET clauses
    **********************************************************************/
    function get_set($params)
    {
        if (!is_array($params)) {
            $this->register_error('get_set() parameter invalid. Expected array');
            return;
        }
        $sql = array();
        foreach ($params as $field => $val) {
            if ($val === true || $val === 'true') $val = 1;
            if ($val === false || $val === 'false') $val = 0;
            switch ($val) {
                case 'NOW()':
                case 'NULL':
                    $sql[] = "$field = $val";
                    break;
                default:
                    $sql[] = "$field = '" . $this->escape($val) . "'";
            }
        }
        return implode(', ', $sql);
    }

    function count($all = true, $increase = false)
    {
        if ($increase) {
            $this->num_queries++;
            $this->conn_queries++;
        }
        return ($all) ? $this->num_queries : $this->conn_queries;
    }
}
