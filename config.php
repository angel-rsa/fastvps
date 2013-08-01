<?php
ini_set('register_globals', 0);
date_default_timezone_set('Europe/Moscow');

// настройка режима отладки
if(!defined("DEBUG_MODE")) define("DEBUG_MODE", false);
if(DEBUG_MODE){
    // режим отладки
    set_time_limit(60);         // увеличенное время исполнения
    error_reporting(E_ALL);     // расширенный вывод ошибок
} else {
    // "боевой" режим
    error_reporting(0);         // сокрытие информации об ошибках
}

// абсолютный путь к корню сайта
$root = realpath( "." );
$os = php_uname('s');
if(strtolower(substr( $os, 0, 3 ) ) == "win" )  $root = str_replace( "\\", '/', $root );
define( "ROOT_PATH", $root );
// абсолютные пути к основным рабочим папкам
define( "LIB_PATH", ROOT_PATH . "/lib" );
define( "CACHE_PATH", ROOT_PATH . "/cache" );
define( "TEMPLATES_PATH", ROOT_PATH . "/templates" );
// корневой URL
if(isset($_SERVER['HTTP_HOST'])){
    define( "ROOT_URL", $_SERVER['HTTP_HOST'] . (!empty($_SERVER['PHP_SELF']) ? dirname($_SERVER['PHP_SELF']) : '') .'/');
} else {
    define( "ROOT_URL", '/' );
}

// подключение общих функций
include(LIB_PATH."/functions.common.php");
// подключение общих класов
require_once(LIB_PATH."/class.host.php");
require_once(LIB_PATH."/class.storage.php");
require_once(LIB_PATH."/class.convert.php");
require_once(LIB_PATH."/class.cache.php");
require_once(LIB_PATH.'/class.template.php');

// инициализация ключевых классов фреймворка
Host::Init();
Session::Init();
Cookie::Init();
Request::Init();

?>
