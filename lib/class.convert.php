<?php
if(!defined("TYPE_STRING")) define( "TYPE_STRING", "string" );
if(!defined("TYPE_INTEGER")) define( "TYPE_INTEGER", "integer" );
if(!defined("TYPE_FLOAT")) define( "TYPE_FLOAT", "float" );
if(!defined("TYPE_BOOLEAN")) define( "TYPE_BOOLEAN", "boolean" );
if(!defined("TYPE_ARRAY")) define( "TYPE_ARRAY", "array" );
if(!defined("TYPE_OBJECT")) define( "TYPE_OBJECT", "object" );
if(!defined("TYPE_PARAMETER")) define( "TYPE_PARAMETER", "parameter" );
if(!defined("TYPE_DATETIME")) define( "TYPE_DATETIME", "datetime" );
if(!defined("TYPE_DATE")) define( "TYPE_DATE", "date" );
if(!defined("TYPE_TIME")) define( "TYPE_TIME", "time" );

/**
* Convert Class
*/
class Convert {
    /* --- to String Convertors --- */
    public static function ToString( $value ) {
        if( $value===null ) return null;
        if( is_object( $value ) || is_array( $value )) {
            $result = serialize($value);
        } else $result = (string) $value;
        return $result;
    }
    public static function ToStr( $value ) {
        return self::ToString( $value );
    }
    
    /* --- to Integer Convertors --- */
    public static function ToInt( $value ) {
        if( is_object( $value ) || is_array( $value ) || $value===null ) return null;
        $result = (int) $value;
        return $result;
    }
    public static function ToInteger( $value ) {
        return self::ToInt( $value );
    }

    /* --- to Float Convertors --- */
    public static function ToFloat( $value ) {
        if( is_object( $value ) || is_array( $value ) || $value===null ) {
            return null;
        }
        $result = (float) $value;
        return $result;
    }
    public static function ToDouble( $value ) {
        return self::ToFloat( $value );
    }

    /* --- to Boolean Convertors --- */
    public static function ToBoolean( $value ) {
        if( $value===null ) {
            return null;
        }
        $result = (bool) $value;
        return $result;
    }
    public static function ToBool( $value ) {
        return self::ToBoolean( $value );
    }
    
    /* --- to Array Convertors --- */
    public static function ToArray( $value ) {
        $result = (array) $value;
        return $result;
    }

    /* --- to Object Convertors --- */
    public static function ToObject( $value ) {
        $result = (object) $value;
        return $result;
    }
    
    /* --- to Date and Time Convertors --- */
    public static function ToDateTime( $value ) {
        if(empty($value)) return null;
        $datetime = date('Y-m-d H:i:s',strtotime(self::ToString($value)));
        return $datetime;
    }
    public static function ToDate( $value ) {
        if(empty($value)) return null;
        $datetime = date('Y-m-d',strtotime(self::ToString($value)));
        return $datetime;
    }
    public static function ToTime( $value ) {
        if(empty($value)) return null;
        $datetime = date('H:i:s',strtotime(self::ToString($value)));
        return $datetime;
    }

    /**
     * Common convert function
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public static function ToValue( &$value, $type = "parameter" ) {
        switch( $type ) {
            case TYPE_STRING:
                return self::ToString( $value );
            case TYPE_INTEGER:
                return self::ToInt( $value );
            case TYPE_FLOAT:
                return self::ToFloat( $value );
            case TYPE_BOOLEAN:
                return self::ToBoolean( $value );
            case TYPE_ARRAY:
                return self::ToArray( $value );
            case TYPE_OBJECT:
                return self::ToObject( $value );
            case TYPE_PARAMETER:
                return $value;
            case TYPE_DATE:
                return self::ToDate( $value );
            case TYPE_TIME:
                return self::ToTime( $value );
            case TYPE_DATETIME:
                return self::ToDateTime( $value );
            default:
                return null;
        }
    }
    
    /**
    * @desc Convert object or array to array with keys, represented in sourse array|object as one of fieldvalues
    * @param array|object sourse data set
    * @param string Field name, which will be a key
    * @param boolean convert values with same keys in array (or rewrite it)
    */
    public static function Collapse( $sourceObjects, $collapseKeys, $toArray = true) {
        if ( empty( $sourceObjects ) ) {
            return null;
        }
        $result = array();
        $keys = explode(',', $collapseKeys);
        foreach ( $sourceObjects as $object ) {
            $keystring = '';
            foreach($keys as $key){
                if(is_int($key)) $keystring .= '['.$object[trim($key)].']';
                else $keystring .= "['".$object[trim($key)]."']";
            }
            if($toArray) $str = '$result'.$keystring.'[] = $object;';
            else $str = '$result'.$keystring.' = $object;';
            @eval($str);
        }
        return $result;
    }
    
    /**
    * @desc Sort named array by key definde values
    * @param array sourse data set
    * @param string key name
    * @param boolean convert values with same keys in array (or rewrite it)
    */
    public static function ArrayKeySort($array, $key, $toArray = false){
        $array = self::Collapse($array,$key,$toArray);
        $keys = array_keys($array);
        natsort($keys);
        $result = array();
        foreach($keys as $key){
            $result[$key] = $array[$key];
        }
        return $result;
    }

    /**
    * Перевод русского текта в транслит (с возможностью генерировать имя файла)
    * @param string исходная строка
    * @param boolean режим имени файла (некоторые символы не должны быть использованы в именах файлов)
    */
    public static function ToTranslit($str,$isFileName=false){
        if($isFileName){
            $tbl= array(
            'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ж'=>'g', 'з'=>'z',
            'и'=>'i', 'й'=>'y', 'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n', 'о'=>'o', 'п'=>'p',
            'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'ы'=>'i', 'э'=>'e', 'А'=>'A',
            'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ж'=>'G', 'З'=>'Z', 'И'=>'I',
            'Й'=>'Y', 'К'=>'K', 'Л'=>'L', 'М'=>'M', 'Н'=>'N', 'О'=>'O', 'П'=>'P', 'Р'=>'R',
            'С'=>'S', 'Т'=>'T', 'У'=>'U', 'Ф'=>'F', 'Ы'=>'I', 'Э'=>'E', 'ё'=>"yo", 'х'=>"h",
            'ц'=>"ts", 'ч'=>"ch", 'ш'=>"sh", 'щ'=>"shch", 'ъ'=>"_", 'ь'=>"_", 'ю'=>"yu", 'я'=>"ya",
            'Ё'=>"YO", 'Х'=>"H", 'Ц'=>"TS", 'Ч'=>"CH", 'Ш'=>"SH", 'Щ'=>"SHCH", 'Ъ'=>"_", 'Ь'=>"_",
            'Ю'=>"YU", 'Я'=>"YA", ' '=>'_', ','=>'_', '?'=>'_', '*'=>'_', '\\'=>'_', '#'=>'_', 
            '&'=>'_', ':'=>'_'
            );
            return strtolower(strtr($str, $tbl));
        } else {
            $tbl= array(
            'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ж'=>'g', 'з'=>'z',
            'и'=>'i', 'й'=>'y', 'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n', 'о'=>'o', 'п'=>'p',
            'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'ы'=>'i', 'э'=>'e', 'А'=>'A',
            'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ж'=>'G', 'З'=>'Z', 'И'=>'I',
            'Й'=>'Y', 'К'=>'K', 'Л'=>'L', 'М'=>'M', 'Н'=>'N', 'О'=>'O', 'П'=>'P', 'Р'=>'R',
            'С'=>'S', 'Т'=>'T', 'У'=>'U', 'Ф'=>'F', 'Ы'=>'I', 'Э'=>'E', 'ё'=>"yo", 'х'=>"h",
            'ц'=>"ts", 'ч'=>"ch", 'ш'=>"sh", 'щ'=>"shch", 'ъ'=>"", 'ь'=>"", 'ю'=>"yu", 'я'=>"ya",
            'Ё'=>"YO", 'Х'=>"H", 'Ц'=>"TS", 'Ч'=>"CH", 'Ш'=>"SH", 'Щ'=>"SHCH", 'Ъ'=>"", 'Ь'=>"",
            'Ю'=>"YU", 'Я'=>"YA"
            );
            return strtr($str, $tbl);
        }
    }
    
    /**
    * Дата в русском формате
    * @param mixed $datetime
    */
    public static function ru_date($datetime=null){
        if(empty($datetime)) $datetime = time();
        if(!is_numeric($datetime)) $datetime = strtotime($datetime);
        $months = array('января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря');
        return date('j',$datetime).' '.$months[intval(date('n',$datetime))-1].' '.date('Y',$datetime);
    }
    /**
    * Месяц в русском формате
    * @param mixed $datetime
    */
    public static function ru_month($datetime=null,$basic_form=false){
        if(!is_numeric($datetime)) $datetime = strtotime($datetime);
        $months = array('января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря');
        $months_basic = array('январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь');
        return $basic_form ? $months_basic[intval(date('n',$datetime))-1] : $months[intval(date('n',$datetime))-1];
    }


    public static function json_encode($a=false, $try_internal=true){
        if($try_internal){
            $funcs = get_defined_functions();
            if(in_array('json_encode',$funcs['internal'])) return json_encode($a);
        }
        if ($a===null) return 'null';
        if ($a === false) return 'false';
        if ($a === true) return 'true';
        if (is_scalar($a)){
            if (is_float($a)){
                return floatval(str_replace(",", ".", strval($a)));
            }
            if (is_string($a)){
                static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
                return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
            } else return $a;
        }
        $isList = true;
        for ($i = 0, reset($a); $i < count($a); $i++, next($a)){
            if (key($a) !== $i){
                $isList = false;
                break;
            }
        }
        $result = array();
        if ($isList){
            foreach ($a as $v) $result[] = self::json_encode($v);
            return '[' . join(',', $result) . ']';
        } else {
            foreach ($a as $k => $v) $result[] = self::json_encode($k).':'.self::json_encode($v);
            return '{' . join(',', $result) . '}';
        }
    }
    
    public static function ArrayToString($arr){
        return addslashes(serialize($arr));
    }
    
    public static function StringToArray($str){
        if(empty($str)) return array();
        return unserialize(stripslashes($str));
    }
    
    public static function StringGetToArray($str){
        parse_str($str, $return);
        return $return;
    }
    public static function ArrayToStringGet($array, $delimiter='&'){
        if(empty($array)) return '';
        return http_build_query($array,'var_',$delimiter);
    }

}

/**
* Validator Class
*/
class Validate{

    /**
     * Check for valid value by type
     * @param mixed $value
     * @param const $type
     * @return bool
     */
    public static function isValidType( $value, $type ) {
        switch( $type ) {
            case TYPE_STRING:
                return true;
            case TYPE_INTEGER:
                return self::Digit( $value , SITE_CHARSET=='UTF-8');
            case TYPE_FLOAT:
                return self::Numeric( $value );
            case TYPE_BOOLEAN:
                return ($value == 1 || $value == 0);
            case TYPE_ARRAY:
                return (Convert::ToArray( $value ) == $value);
            case TYPE_DATE:
            case TYPE_TIME:
            case TYPE_DATETIME:
                return !empty( $value );
            case TYPE_OBJECT:
                return (Convert::ToObject( $value ) == $value);
            case TYPE_PARAMETER:
                return true;
            default:
                return false;
        }
    }

    /**
     * Check for valid value by min value
     * @param mixed $value
     * @param mixed $min
     * @param const $type
     * @return bool
     */
    public static function IsValidMax( $value, $max, $type ) {
        switch( $type ) {
            case TYPE_STRING:
                return mb_strlen( $value, SITE_CHARSET ) <= $max;
            case TYPE_INTEGER:
            case TYPE_FLOAT:
                return $value <= $max;
            case TYPE_ARRAY:
                return count( $value ) <= $max;
            case TYPE_PARAMETER:
                return true;
            default:
                return false;
        }
    }

    /**
     * Check for valid value by min value
     * @param mixed $value
     * @param mixed $min
     * @param const $type
     * @return bool
     */
    public static function IsValidMin( $value, $min, $type ) {
        switch( $type ) {
            case TYPE_STRING:
                return mb_strlen( $value, SITE_CHARSET ) >= $min;
            case TYPE_INTEGER:
            case TYPE_FLOAT:
                return $value >= $min;
            case TYPE_ARRAY:
                return count( $value ) >= $min;
            case TYPE_PARAMETER:
                return true;
            default:
                return false;
        }
    }

    /**
    * Validate email, commonly used characters only
    * @param   string   email address
    * @return  boolean
    */
    public static function isEmail( $email ) {
        return (bool) preg_match( '/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD', (string) $email );
    }

    /**
    * Checks whether a string consists of digits only (no dots or dashes).
    * @param   string   input string
    * @param   boolean  trigger UTF-8 compatibility
    * @return  boolean
    */
    public static function Digit( $str, $utf8 = FALSE ) {
        return ($utf8 === TRUE) ? (bool) preg_match( '/^\pN++$/uD', (string) $str ) : ctype_digit( (string) $str );
    }
    /**
    * Checks whether a string is a valid number (negative and decimal numbers allowed).
    * @param   string   input string
    * @return  boolean
    */
    public static function Numeric( $str ) {
        return (is_numeric( $str ) and preg_match( '/^[-0-9.]++$/D', (string) $str ));
    }


    public static function validateParams($params, $mapping){
        $errors = array();
        if(!is_array($params) || !is_array($mapping)) return array();
        foreach($params as $key=>$value){
            if(isset($mapping[$key])){
                foreach($mapping[$key] as $type=>$critery){
                    switch($type){
                        case 'type':
                            if(!self::isValidType($value,$critery)) $errors[$key] = $type;
                            break;
                        case 'min':
                            if(!self::IsValidMin($value,$critery,$mapping[$key]['type'])) $errors[$key] = $type;
                            break;
                        case 'max':
                            if(!self::IsValidMax($value,$critery,$mapping[$key]['type'])) $errors[$key] = $type;
                            break;
                        case 'allow_empty':
                            if(empty($critery) && empty($value)) $errors[$key] = $type;
                            break;
                        case 'allow_null':
                            if(empty($critery) && $value===null) $errors[$key] = $type;
                            if(isset($errors[$key]) && $errors[$key]=='type' && $critery && $value===null) unset($errors[$key]);
                            break;
                        case 'default':
                        default:
                            break;
                    }
                }
            }
        }
        return $errors;
    }
}
?>