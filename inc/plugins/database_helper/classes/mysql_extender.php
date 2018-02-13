<?php
/**
 * Created by PhpStorm.
 * User: Latios
 * Date: 1/24/2018
 * Time: 9:03 AM
 */

class mysql_extender extends DB_MySQL
{
    /**
     * @var int The maximum results in a select query.  count_query overides this.
     */
    private $max_limit;

    /**
     * @var int If the limit clause has been applied.
     */
    private $limit_safe = 0;

    /**
     * @var array An array for the name of scripts that should be skipped.
     * Use $config['database']['limit_skip'] = array("file1.php", "file2.php");
     * Default is array("search.php")
     */
    private $limit_skip = array();

    /**
     * @var int If a table can be dropped.  Used with the query method to prevent a table from being dropped
     * either accidentally or maliciously.  Only calls to the drop_table method can execute this query.
     */
    private $drop_table = 0;

    /**
     * @var int If a table can be truncated.  Used with query method to prevent accidents or attacks.
     */
    private $truncate_table = 0;

    /**
     * @var int If a table can have rows deleted.
     */
    private $delete_rows = 0;

    /**
     * @var array A cache of all the foreign keys.
     */
    public $foreign_keys = array();

    /**
     * mysql_extender constructor. We need to do a few things to make this work.
     */
    public function __construct()
    {
        global $config, $lang;
        parent::connect($config['database']);
        $this->set_table_prefix(TABLE_PREFIX);
        $this->type = $config['database']['type'];
        if(isset($config['database']['limit']))
        {
            $this->max_limit = $config['database']['limit'];
        }
        else
        {
            $this->max_limit = 50;
        }
        if(isset($config['database']['limit_skip']))
        {
            $this->limit_skip = $config['database']['limit_skip'];
        }
        else
        {
            $this->limit_skip[0] = "search.php";
        }
        // THIS_SCRIPT is defined in global.php
        if(in_array(THIS_SCRIPT, $this->limit_skip))
        {
            $this->limit_safe = 2;
        }
        $lang->load("database_helper");
    }

    public function query($string, $hide_errors=0, $write = 0)
    {
        global $lang;
        // Make sure a table isn't randomly dropped.
        if(strpos($string, "DROP TABLE") !== false && $this->drop_table == 0)
        {
            $this->error($lang->database_helper_drop);
            exit;
        }
        // Check for truncate.
        if(strpos($string, "TRUNCATE TABLE") !== false && $this->truncate_table == 0)
        {
            $this->error($lang->database_helper_truncate);
            exit;
        }
        // Check for deletion
        if(strpos($string, "DELETE FROM") !== false && $this->delete_rows == 0)
        {
            $this->error($lang->database_helper_delete);
            exit;
        }
        // Check select statements for a limit.
        if(strpos($string, "SELECT") !== false && $this->limit_safe == 0)
        {
            if(strpos($string, "LIMIT") !== false)
            {
                if(preg_match("/\A(.*)LIMIT\s([0-9]{1,})\Z/is", $string))
                {
                    $limitline = preg_replace("/\A(.*)LIMIT\s([0-9]{1,})\Z/is", "$2", $string);
                    $limit = $this->enforce_limit_clause($limitline);
                    if($limit != $limitline)
                    {
                        $string = preg_replace("/\A(.*)LIMIT\s([0-9]{1,})\Z/is", "$1 LIMIT " . $limit, $string);
                    }
                }
                if(preg_match("/\A(.*)LIMIT\s([0-9]{1,})([,\s]){1,}([0-9]{1,})\Z/is", $string))
                {
                    $limitline = preg_replace("/\A(.*)LIMIT\s([0-9]{1,})([,\s]){1,}([0-9]{1,})\Z/is", "$4", $string);
                    $limit = $this->enforce_limit_clause($limitline);
                    if($limit != $limitline)
                    {
                        $string = preg_replace("/\A(.*)LIMIT\s([0-9]{1,})([,\s]){1,}([0-9]{1,})\Z/is", "$1 LIMIT $2 , $3 " . $limit , $string);
                    }
                }
            }
            else
            {
                // No limit clause at all.  Add a limit then.
                $string .= " LIMIT " . $this->max_limit;
            }
        }
        $beforetime = microtime();
        $query = parent::query($string, $hide_errors, $write);
        $aftertime = microtime();
        $speed = $aftertime - $beforetime;
        if($speed >= 2)
        {
            $this->log_slow_query($string, $speed);
        }
        return $query;
    }

    /**
     * @param string $string The query to execute.
     * @param int $hide_errors If we are showing errors.
     * @return mixed The query data.
     */
    public function write_query($string, $hide_errors=0)
    {
        $query = $this->query($string, $hide_errors, 1);
        return $query;
    }

    /**
     * @param string $name The name of the constraint.
     * @param string $parent_table The table to protect.
     * @param string $parent_column The column to protect.
     * @param string $child_table The table that can have data inserted.
     * @param string $child_column The column that can have data inserted.
     * @param string $on_update What to do on an update.
     * @param string $on_delete What to do on a delete.
     * @return mixed the id of the foreign key in the foreign_key table.  False on failure.
     */
    public function add_foreign_key($name, $parent_table, $parent_column, $child_table, $child_column, $on_update="CASCADE", $on_delete="CASCADE")
    {
        $sql = "ALTER TABLE `" . TABLE_PREFIX . $child_table . "` ADD CONSTRAINT `" . $name .
            "` FOREIGN KEY (`" . $child_column . "`) 
            REFERENCES `" . TABLE_PREFIX . $parent_table . "` (`" . $parent_column . "`)";
        $allowed_actions = array("restrict", "cascade", "set null", "no action", "set default");
        $new_foreign_key = array(
            "constraint_name" => $name,
            "parent_table" => $parent_table,
            "parent_column" => $parent_column,
            "child_table" => $child_table,
            "child_column" => $child_column,
            "on_update" => $on_update,
            "on_delete" => $on_delete
        );
        if(in_array($on_delete, $allowed_actions))
        {
            $sql .= " ON DELETE " . $on_delete;
        }
        else
        {
            $new_foreign_key['on_delete'] = "";
        }
        if(in_array($on_update, $allowed_actions))
        {
            $sql .= " ON UPDATE " . $on_update;
        }
        else
        {
            $new_foreign_key['on_delete'] = "";
        }
        $this->write_query($sql);
        array_map(array($this, 'escape_string'), $new_foreign_key);
        $fid = $this->insert_query("foreign_keys", $new_foreign_key);
        return $fid;
    }

    /**
     * @param string $name The name of the constraint.
     * @param string $table The table of the constraint.
     * @return mixed Result
     */
    public function drop_foreign_key($name, $table)
    {
        $query = "ALTER TABLE " . TABLE_PREFIX . $table . " DROP FOREIGN KEY " . $name;
        $query = $this->write_query($query);
        $this->delete_query("foreign_keys", "constraint_name='" . $name . "' AND parent_table='" . $table . "'");
        return $query;
    }

    /**
     * @return array A multidimensional array of foreign keys.
     */
    public function get_foreign_keys()
    {
        if(isset($this->foreign_keys))
        {
            return $this->foreign_keys;
        }
        $this->limit_safe = 1;
        $query = parent::simple_select("foreign_keys", "*");
        while($key = $this->fetch_clean_array($query))
        {
            $field = $key['constraint_name'];
            $this->foreign_keys[$field] = $key;
        }
        $this->limit_safe = 0;
        return $this->foreign_keys;
    }

    /**
     * This function reduces the amount of code to figure out how many rows are in a result set.
     * @param string $table The table.
     * @param string $fields the column(s) to use.  The primary key is ideal.
     * @param string $conditions Where conditions.
     * @return mixed The number of records matching the conditions.
     */
    public function count_query($table, $fields="*", $conditions)
    {
        $sql = "SELECT COUNT(" . $fields . ") as total
        FROM " . TABLE_PREFIX . $table;
        if($conditions)
        {
            $sql .= " WHERE " . $conditions;
        }
        if($this->limit_safe != 2)
        {
            $this->limit_safe = 1;
            $query = $this->query($sql);
            $this->limit_safe = 0;
        }
        else
        {
            $query = $this->query($sql);
        }
        return $this->fetch_field($query, "total");
    }

    /**
     * @param mixed $value The limit to test.
     * @return int|mixed A safe limit.
     */
    public function enforce_limit_clause($value)
    {
        if(!is_numeric($value))
        {
            return 50;
        }
        if($value > $this->max_limit)
        {
            return $this->max_limit;
        }
        return (int) $value;
    }

    /**
     * @param string $table The table to drop.
     */
    public function drop_table($table, $hard=false, $prefix=true)
    {
        $this->drop_table = 1;
        $sql = "DROP TABLE ";
        if($hard)
        {
            $sql .= " IF EXISTS ";
        }
        if($prefix)
        {
            $sql .= $this->table_prefix;
        }
        $sql .= $table;
        $this->write_query($sql);
        $this->drop_table = 0;
    }

    /**
     * @param string $table The table to empty.
     */
    public function truncate_table($table)
    {
        $this->truncate_table = 1;
        $this->write_query("TRUNCATE TABLE " . $this->table_prefix . $table);
        $this->truncate_table = 0;
    }

    /**
     * @param string $table The table.
     * @param string $where The where clause
     * @param string $limit An optional limit.
     * @return mixed The query data.
     */
    public function delete_query($table, $where="", $limit="")
    {
        $this->delete_rows = 1;
        $sql = "DELETE FROM " . $this->table_prefix . $table;
        if($where)
        {
            $sql .= " WHERE " . $where;
        }
        if($limit)
        {
            $sql .= " LIMIT " . $limit;
        }
        $query = $this->write_query($sql);
        $this->delete_rows = 0;
        return $query;
    }

    /**
     * @param resource $resource A resource query.
     * @param int $resulttype What kind of array is wanted.
     * @return array An array that has had htmlspecialchars_uni called on it.
     */
    public function fetch_clean_array($resource, $resulttype = MYSQL_ASSOC)
    {
        $array = parent::fetch_array($resource, $resulttype);
        array_map("htmlspecialchars_uni", $array);
        return $array;
    }

    /**
     * @param resource $resource A resource query.
     * @param string $field The field to fetch.
     * @param bool $row Where to point the cursor.
     * @return string A safe string.
     */
    public function fetch_clean_field($resource, $field, $row=false)
    {
        $result = parent::fetch_field($resource, $field, $row);
        return htmlspecialchars_uni($result);
    }

    /**
     * @param string $string The query string.
     * @param float $execution_time The time in microseconds that it took to execute.
     */
    public function log_slow_query($string, $execution_time)
    {
        if(defined("THIS_SCRIPT"))
        {
            $this_script = THIS_SCRIPT;
        }
        else
        {
            $this_script = $_SERVER['PHP_SELF'];
        }
        $fopen = fopen(MYBB_ROOT . "/slowquery.log", "a");
        fwrite($fopen, "<slowquery>\n\t<dateline>" . TIME_NOW . "</dateline>\n\t<query>" . $string . "</query>\n\t"
            . "<execution_time>" . format_time_duration($execution_time) . "</execution_time>\n\t<file>" . $file . "</file>"
            . "\n\t<this_script>" . $this_script . "</this_script>\n</slowquery>\n\n");
        fclose($fopen);
    }
}
