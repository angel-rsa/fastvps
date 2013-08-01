<?php
/**
* Набор общих функций
* всегда загружается при старте
*/

// деэкранирование кавычек
function stripslashes_deep($value){
    if(is_array($value)){
        $value = array_map('stripslashes_deep', $value);
    }elseif(!empty($value) && is_string($value)){
        $value = stripslashes($value);
    }
    return $value;
}
// защита от архаичного режима мэджик квотес
if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()){
    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
    $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}
?>
