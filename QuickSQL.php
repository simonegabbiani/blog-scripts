<?php
//QuickSQL.php
//portable SQL library for simple/common functions (BETA)
//(missing: error management + several bug fixes)

//namespace \Database

abstract class SQL {
    const ASSOC = 0x01;//options
    static $defaults = array('vendor' => 'mysql', 'host' => 'localhost', 'dbname' => 'mysql', 'user' => 'root', 'password' => '');
    static $real = array();
    var $ress = array();
    var $last = null;
    abstract protected function on_new();//vendor implementation
    abstract protected function real_query($sql, $assoc);//vendor implementation
    static public function factory($vendor='', $host='', $user='', $pass='', $dbname='') {
        $real = self::$defaults;
        foreach (array_keys(self::$defaults) as $k)
            $real[$k] = ((string)$$k != '') ? $$k : self::$defaults[$k];
        $class = $GLOBALS['SQL_VENDOR_CLASSES'][$real['vendor']];
        $sql = new $class();
        $sql->real =& $real;
        $sql->on_new();
        return $sql;
    }
    public function last() {
        return $ress[count($ress)-1];
    }
    public function query($sql, $options = self::ASSOC) {
        $this->last = $this->real_query($sql, $options & self::ASSOC);
        array_push($this->ress, $this->last);
        //other kind of results or formats
        //if ($options & RET_FIRST_FIELD) return $this->last->ret_first_field();
        return $this->last;
    }
    //abstract protected function ret_first_field();//query option implementation
    //abstract protected function ret_array($options);//query option implementation
}

abstract class SQL_Result implements Iterator {
    abstract public function count();
}

//mysqli interface (PHP5)
$GLOBALS['SQL_VENDOR_CLASSES']['mysql'] = 'SQL_MYSQL';
class SQL_MYSQL extends SQL {
    private $mysqli;
    protected function on_new() {
        $this->mysqli = new mysqli($this->real['host'], $this->real['user'], $this->real['password'], $this->real['dbname']);
    }
    protected function real_query($sql, $assoc) {
        $r = $this->mysqli->query($sql);
        return new SQL_MYSQL_Result($r, $assoc);
    }
}

class SQL_MYSQL_Result extends SQL_Result {
    private $position = 0;
    private $count = 0;
    private $result = null;//mysqli_result
    private $assoc = false;
    private $row;
    public function __construct(/*mysqli_result*/ $r, $assoc) { 
        $this->assoc = $assoc; $this->result = $r; $this->count = $r->num_rows; 
        $this->row = ($assoc ? $r->fetch_assoc() : $r->fetch_row()); }
    public function current () { return $this->row; }
    public function key () { return $this->position; }
    public function next () { $this->row = ($this->assoc ? $this->result->fetch_assoc() : $this->result->fetch_row()); return ++$this->position; }
    public function rewind () { $this->position = 0; $this->result->data_seek(0); }
    public function valid () { return $this->position < $this->count-1; }
    public function count() { return $this->count; }
}


//mysql_xxxxx functions (PHP < 5)
$GLOBALS['SQL_VENDOR_CLASSES']['mysql-old'] = 'SQL_MYSQL_OLD';
class SQL_MYSQL_OLD extends SQL {
    private $conn;
    protected function on_new() {
        $this->conn = mysql_connect($this->real['host'], $this->real['user'], $this->real['password']);
        mysql_select_db($this->real['dbname'], $this->conn);
    }
    protected function real_query($sql, $assoc) {
        if (($r = mysql_query( $sql, $this->conn )) !== false)
            return new SQL_MYSQL_OLD_Result($r, $assoc);
    }
}

class SQL_MYSQL_OLD_Result extends SQL_Result {
    private $position = 0;
    private $count = 0;
    private $result = null;
    private $assoc = false;
    public function __construct($r, $assoc) { 
        $this->assoc = $assoc; $this->result = $r; $this->count = mysql_num_rows($r); }
    public function current () { return $this->assoc ? mysql_fetch_assoc($this->result) : mysql_fetch_row($this->result); }
    public function key () { return $this->position; }
    public function next () { mysql_data_seek($this->result, ++$this->position); return $this->position; }
    public function rewind () { mysql_data_seek($this->result, $this->position = 0); }
    public function valid () { return $this->position < $this->count-1; }
    public function count() { return $this->count; }
}

//postgresql (NOT TESTED!!!)
$GLOBALS['SQL_VENDOR_CLASSES']['postgresql'] = 'SQL_POSTGRESQL';
class SQL_POSTGRES extends SQL {
    private $conn;
    protected function on_new() {
        $this->conn = pg_connect("host={$this->real['host']} user={$this->real['user']} password={$this->real['password']} dbname={$this->real['dbname']}");
    }
    protected function real_query($sql, $assoc) {
        if (($r = pg_query( $this->conn, $this->conn )) !== false)
            return new SQL_POSTGRESQL_Result($r, $assoc);
    }
}

class SQL_POSTGRESQL_Result extends SQL_Result {
    private $position = 0;
    private $count = 0;
    private $result = null;
    private $assoc = false;
    public function __construct($r, $assoc) { 
        $this->assoc = $assoc; $this->result = $r; $this->count = pg_num_rows($r); }
    public function current () { return $this->assoc ? pg_fetch_assoc($this->result) : pg_fetch_row($this->result); }
    public function key () { return $this->position; }
    public function next () { pg_result_seek($this->result, ++$this->position); return $this->position; }
    public function rewind () { pg_result_seek($this->result, $this->position = 0); }
    public function valid () { return $this->position < $this->count-1; }
    public function count() { return $this->count; }
}

?>
