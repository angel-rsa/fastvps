<?php
if(!defined("LIB_PATH")) define("LIB_PATH",'.');
require_once(LIB_PATH."/class.cache.php");
require_once(LIB_PATH."/class.convert.php");

/**
* mysqli DB wrapper
*/
class mysqli_db extends mysqli{
    private $max_allowed_packet = "10M";
    private $connection_timeout = 5;
    private $log_size = 20;
    
    private $db_host = null;
    private $db_user = null;
    private $db_passwd = null;
    private $db_name = null;
    private $db_port = null;
    private $db_socket = null;
    private $db_codepage = 'utf8';
    private $db_charset = 'utf8';
    private $cache_enabled = false;
    public $prefix = '';
   
    public $query_log = array();
    public $executed_query_count = 0;
    public $cached_query_count = 0;
    public $error_message = 0;
    
    public function __construct($parameters){
        $this->db_host      = isset($parameters['db_host']) ? $parameters['db_host'] : ini_get("mysqli.default_host");
        $this->db_port      = isset($parameters['db_port']) ? $parameters['db_port'] : ini_get("mysqli.default_port");
        $this->db_socket    = isset($parameters['db_socket']) ? $parameters['db_socket'] : ini_get("mysqli.default_socket");
        $this->db_user      = isset($parameters['db_user']) ? $parameters['db_user'] : ini_get("mysqli.default_user");
        $this->db_passwd    = isset($parameters['db_pass']) ? $parameters['db_pass'] : ini_get("mysqli.default_pw");;
        $this->db_name      = isset($parameters['db_name']) ? $parameters['db_name'] : "";
        $this->db_codepage  = $this->db_charset = isset($parameters['db_charset']) ? $parameters['db_charset'] : "utf8";
        $this->prefix       = isset($parameters['db_prefix']) ? $parameters['db_prefix'] : "";;
        $this->cache_enabled = class_exists('Cache') && !empty(Cache::$query_cache_time);

        $time = microtime(true);
        parent::init();
        if (!parent::options(MYSQLI_INIT_COMMAND, 'SET AUTOCOMMIT = 0')) {
            $this->error_message = 'Setting MYSQLI_INIT_COMMAND failed';
            return false;
        }
        if (!parent::options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->connection_timeout)) {
            $this->error_message = 'Setting MYSQLI_OPT_CONNECT_TIMEOUT failed';
            return false;
        }
        if (!parent::real_connect($this->db_host, $this->db_user, $this->db_passwd, $this->db_name, $this->db_port, $this->db_socket)) {
            $this->error_message = 'Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error();
            return false;
        }
        if(!parent::set_charset($this->db_charset)){
            $this->error_message = 'Can not set charset!';
            return false;
        }
        $time = round(microtime(true)-$time, 6);
        if(DEBUG_MODE) $this->log_query("DB Class initialization (connect)", $time, true);
    }    

    /**
    * Логирование запросов
    * @param string запрос
    * @param float время исполнения
    */
    public function log_query($query, $time=null, $result=null, $error=''){
        if(sizeof($this->query_log ) >= $this->log_size) array_shift($this->query_log );
        $this->query_log[] = array('query'=>$query, 'time'=>$time, 'result'=>$result, 'error'=>$error);
        $this->executed_query_count++;
    }

    /**
    * экранирование параметра
    * @param mixed параметр
    * @return mixed экранированный параметр
    */
    public function quoted( $value ) {
        if($value===null) $value = "NULL";
        elseif(!Validate::Numeric($value)) $value = "'" . parent::real_escape_string( $value ) . "'";
        return $value;
    }

    /**
    * подготовка строки запроса - подстановка значений вместо знаков '?'
    * @param mixed SQL-запрос
    * @param mixed $args
    */
    private function query_prepare($query, $args){
        $args_count = count($args);
        $aq = preg_split("/\?/msi", $query);
        if(count($aq) != $args_count+1) {
            $this->error_message = 'Query prepare error!';
            return false;
        }
        $query = '';
        for ($i = 0; $i < $args_count; $i++) {
            $query .= array_shift($aq).$this->quoted($args[$i]);
        }
        $query .= array_shift($aq);
        return $query;
    }

    /**
    * Выполнение SQL-запроса
    * @param string query
    * @param mixed variables
    * @return resource
    */
    public function query($query){
        $arg_list = func_get_args();
        $query = array_shift($arg_list);
        if(!empty($arg_list)){
            $query = $this->query_prepare($query, $arg_list);
            if($query===false) return false;
        }
        $time = microtime(true);
        $result = parent::query($query);
        $time = round(microtime(true)-$time, 6);
        if(!$result) $this->error_message = $this->error;
        if(DEBUG_MODE) $this->log_query($query, $time, (bool) $result, $this->error_message);
        if($result===false){
            return false;
        }
        return $result;
    }

    /**
    * возвращает строку выборки (первую, если их несколько)
    * @param string $query строка запроса
    * @param set of values
    */
    public function fetch($query){
        // просмотр дополнительных аргументов
        $numargs = func_num_args();
        if(!$numargs) return false;
        $arg_list = func_get_args();
        $query = array_shift($arg_list);
        if(!empty($arg_list)){
            $query = $this->query_prepare($query, $arg_list);
            if($query===false) return false;
        }
        $result = $this->query( $query );
        if($result===false) return false;
        $arr = false;
        if( $result->num_rows>0 && $result->data_seek(0) ){
            $arr = $result->fetch_assoc();
        }
        if(!is_array($arr)) $arr = false;
        return $arr;
    }

    /**
    * обертка метода fetch с использованием кеширования
    * @param string $query строка запроса
    * @param set of values
    */
    public function fetch_cached($query){
        $numargs = func_num_args();
        if(!$numargs) return false;
        $arg_list = func_get_args();
        $query = array_shift($arg_list);
        if(!empty($arg_list)){
            $query = $this->query_prepare($query, $arg_list);
            if($query===false) return false;
        }
        // проверка кэша
        if($this->cache_enabled){
            $q = Cache::Read($query, CACHE_TYPE_QUERY);
            if($q!==null){
                $this->cached_query_count++;
                return $q;
            }
        }
        $arr = $this->fetch($query);
        // запись результата в кэш
        if($this->cache_enabled){
            Cache::Write($query, $arr, CACHE_TYPE_QUERY);
        }
        return $arr;
    }

    /**
    * возвращает массив строк выборки (если заан ключ, то именованный по значениям этого ключевого поля)
    * @param string строка запроса
    * @param mixed name of primary field or false
    * @param set of values
    */
    public function fetchall($query, $byPrimary = false){
        $numargs = func_num_args();
        if(!$numargs) return false;
        $arg_list = func_get_args();
        $query = array_shift($arg_list);
        if(!empty($arg_list)) @array_shift($arg_list);
        if(!empty($arg_list)){
            $query = $this->query_prepare($query, $arg_list);
            if($query===false) return false;
        }
        $result = $this->query( $query );
        if($result===false) return false;
        $r = array();
        while($a = $result->fetch_assoc()) {
            if( !empty($byPrimary) && !empty($a[$byPrimary]) && (is_numeric($a[$byPrimary]) || is_string($a[$byPrimary]))) {
                $r[$a[$byPrimary]] = $a;
            } else {
                $r[] = $a;
            }
        }
        return $r;
    }
    
    /**
    * обертка метода fetchall с использованием кэширования
    * @param string строка запроса
    * @param mixed name of primary field or false
    * @param set of values
    */
    public function fetchall_cached($query, $byPrimary = false){
        $numargs = func_num_args();
        if(!$numargs) return false;
        $arg_list = func_get_args();
        $query = array_shift($arg_list);
        if(!empty($arg_list)) @array_shift($arg_list);
        if(!empty($arg_list)){
            $query = $this->query_prepare($query, $arg_list);
            if($query===false) return false;
        }
        // проверка кэша
        if($this->cache_enabled){
            $str = $query.($byPrimary ? ' /* ARRAY BY '.$byPrimary.' */' : '');
            $q = Cache::Read($str, CACHE_TYPE_QUERY);
            if($q!==null){
                $this->cached_query_count++;
                return $q;
            }
        }
        $arr = $this->fetchall($query, $byPrimary);
        // запись результата в кэш
        if($this->cache_enabled && $arr!==false){
            $str = $query.($byPrimary ? ' /* ARRAY BY '.$byPrimary.' */' : '');
            Cache::Write($str, $arr, CACHE_TYPE_QUERY);
        }
        return $arr;
    }

    /**
    * Получение описания таблицы
    * @param string $tablename
    * @return array
    */
    public function getTableInfo( $tablename ) {
        $query = "DESC `" . $tablename . "`";
        return $this->fetchall( $query, "Field" );
    }

    /**
    * Обновление строки из ассоциированного массива
    * @param string таблица для обновления
    * @param array ассоциированный массив с данными (ключевое поле должно присутствовать)
    * @param string название (ключ) ключевого поля (primary key)
    * @param bool обновлять значения, если они NULL
    * @return bool
    */
    public function updateFromArray($tablename,$array,$keyfield,$update_null=false){
        $fields = array();
        $tablemap = $this->getTableInfo($tablename);
        $tablemap_keys = array_keys($tablemap);
        foreach($array as $key=>$value){
            if( !in_array($key,$tablemap_keys) ) continue;
            if( $key==$keyfield || ($value===null && !$update_null) ) continue;
            $fields[] = "`".$key."` = ".$this->quoted($value);
        }
        $query = "UPDATE `".$tablename."` SET ".implode(', ',$fields)." WHERE `".$keyfield."`='".$array[$keyfield]."'";
        return $this->query($query);
    }
    
    /**
    * Добавление строки из ассоциированного массива
    * @param string таблица для добавления
    * @param array ассоциированный массив с данными
    * @param string название (ключ) ключевого поля (primary key) (не добавляется если указано)
    * @param bool добавлять значения, если они NULL
    * @return bool
    */
    public function insertFromArray($tablename,$array,$keyfield=false,$update_null=false){
        $fields = $values = array();
        $tablemap = $this->getTableInfo($tablename);
        $tablemap_keys = array_keys($tablemap);
        foreach($array as $key=>$value){
            if( !in_array($key, $tablemap_keys)) continue;
            if($key==$keyfield || ($value===null && !$update_null)) continue;
            $fields[] = "`".$key."`";
            $values[] = $this->quoted($value);
        }
        $query = "INSERT INTO `".$tablename."` (".implode(',',$fields).") VALUES (".implode(',',$values).")";
        return $this->query($query);
    }

    /**
    * Создание новой пустой записи по описанию таблицы
    * @param string таблица
    * @return array запись
    */
    public function prepareNewRecord($tablename){
        $structure = $this->getTableInfo($tablename);
        $array = array();
        foreach($structure as $field=>$desc){
            switch($desc['Type']){
                case 'datetime':
                    $array[$field] = date('Y-m-d H:i:s');
                    break;
                case 'date':
                    $array[$field] = date('Y-m-d');
                    break;
                case 'time':
                    $array[$field] = date('H:i:s');
                    break;
                case 'timestamp':
                    $array[$field] = time();
                    break;
                case 'year':
                    $array[$field] = date('Y');
                    break;
            }
            if($desc['Default']!==null) $array[$field] = $desc['Default'];
            elseif($desc["Key"]!="PRI") $array[$field] = null;
        }
        return $array;
    }

    /**
    * обертка метода prepareNewRecord с использованием кеширования
    * @param string таблица
    * @return array запись
    */
    public function prepareNewRecord_cached($tablename){
        // проверка кэша
        if($this->cache_enabled){
            $str = "NEW RECORD for $tablename";
            $q = Cache::Read($str, CACHE_TYPE_QUERY);
            if($q!==null){
                $this->cached_query_count++;
                return $q;
            }
        }
        $array = $this->prepareNewRecord($tablename);
        // запись результата в кэш
        if($this->cache_enabled){
            $str = "NEW RECORD for $tablename";
            Cache::Write($str, $array, CACHE_TYPE_QUERY);
        }
        return $array;
    }
}
?>