<?php
/**
* Class Host (work with urls and paths)
*/
class Host {
    public static $requested_uri = '';
    public static $scheme = "http";
    public static $host = "";
    public static $port = "";
    public static $path = "";
    public static $query = "";
    public static $fragment = "";
    public static $user = "";
    public static $pass = "";
    public static $remote_user_ip = "";
    public static $forwarded_user_ip = "";
    public static $root_url = "";

    public static function Init(){
        self::$requested_uri = $_SERVER['REQUEST_URI'];
        self::$requested_uri = trim(self::$requested_uri,'/');
        if(!empty(self::$requested_uri)) $url_info = parse_url(self::$requested_uri); else $url_info = array();
        if(empty($url_info['scheme'])){
            self::$scheme = "http";
            if(getenv("HTTPS") == "on" ) self::$scheme = "https";
        } else self::$scheme = $url_info['scheme'];
        self::$port = !empty($url_info['port']) ? $url_info['port'] : getenv("SERVER_PORT");
        self::$host = !empty($url_info['host']) ? $url_info['host'] : (getenv("HTTP_HOST") ? getenv("HTTP_HOST") : getenv("SERVER_NAME"));
        self::$host = rtrim( self::$host, ":" . self::$port );
        self::$path = !empty($url_info['path']) ? trim($url_info['path'],'/') : "";
        self::$query = !empty($url_info['query']) ? trim($url_info['query'],'?') : "";
        self::$fragment = !empty($url_info['fragment']) ? trim($url_info['fragment'],'#') : "";
        self::$user = !empty($url_info['user']) ? $url_info['user'] : "";
        self::$pass = !empty($url_info['pass']) ? $url_info['pass'] : "";

        if((self::$scheme=="http" && self::$port=="80") || (self::$scheme=="https" && self::$port=="443") ) {
            self::$root_url = sprintf( "%s://%s", self::$scheme, self::$host.(!empty($_SERVER['PHP_SELF']) ? dirname($_SERVER['PHP_SELF']) : ''));
        } else {
            self::$root_url = sprintf( "%s://%s:%s", self::$scheme, self::$port, self::$host);
        }
        self::getUserIp(false);
        self::getUserIp(true);
        // сохраняем данные об IP пользователя
        if(!defined("REMOTE_USER_IP")) define( "REMOTE_USER_IP", self::$remote_user_ip);
        if(!defined("FORWARDED_USER_IP")) define( "FORWARDED_USER_IP", self::$forwarded_user_ip);
    }

    /**
    * Получение абсолютного URL
    * @param string URI (от корня) или URL (полный)
    * @return string URL (абсолютный)
    */
    public static function getWebPath( $uri="" ) {
        $uri = trim($uri,'/');
        $url_info = parse_url($uri);
        if(empty($url_info['scheme'])) $url_info['scheme'] = self::$scheme;
        if(empty($url_info['host'])) $url_info['host'] = self::$host;
        $url = "";
        // <схема>://<логин>:<пароль>@<хост>:<порт>/<URL‐путь>?<параметры>#<якорь>
        if(isset($url_info['scheme'])) $url .= $url_info['scheme']."://";
        if(isset($url_info['user']) && isset($url_info['pass'])) $url .= $url_info['user'].':'.$url_info['pass'].'@';
        if(isset($url_info['host'])) $url .= $url_info['host'];
        if(!empty($url_info['port'])) $url .= ':'.$url_info['port'];
        $url .= '/';
        if(!empty($url_info['path'])) $url .= $url_info['path'].(strpos($url_info['path'],'.')===false ? '/' :'');
        if(isset($url_info['query'])) $url .= '?'.$url_info['query'];
        if(isset($url_info['fragment'])) $url .= '#'.$url_info['fragment'];
        return $url;
    }

    /**
     * Получение физического пути файла по uri
     * @param string uri
     * @return string
     */
    public static function getRealPath( $uri='/' ) {
        return realpath(sprintf( "%s/%s", ROOT_PATH, trim($uri,'/')));
    }

    /**
    * Получение Реферальной ссылки
    * @return mixed referer link or false
    */
    public static function getRefererURL() {
        return getenv('HTTP_REFERER');
    }
    
    /**
    * Redirects to location
    * @param string $location
    */
    public static function Redirect( $location, $permanent=true ) {
        $location = self::getWebPath( $location );
        if( !headers_sent() ) {
            if($permanent) header('HTTP/1.0 301 Moved Permanently');
            else header('HTTP/1.0 302 Moved Temporarily');
            header( "Location: " . $location );
        }
        exit();
    }

    /**
    * Получение IP пользователя
    * @param bool если true - IP через прокси, если false - прямой IP
    * @return string IP
    */
    public static function getUserIp($forwarded=false){
        if($forwarded){ if(!empty(self::$forwarded_user_ip)) return self::$forwarded_user_ip; }
        else{ if(!empty(self::$remote_user_ip)) return self::$remote_user_ip; }
        if(!$forwarded) $user_ip = getenv('REMOTE_ADDR');
        elseif(getenv('HTTP_FORWARDED_FOR')) $user_ip = getenv('HTTP_FORWARDED_FOR');
        elseif(getenv('HTTP_X_FORWARDED_FOR')) $user_ip = getenv('HTTP_X_FORWARDED_FOR');
        elseif(getenv('HTTP_X_COMING_FROM')) $user_ip = getenv('HTTP_X_COMING_FROM');
        elseif(getenv('HTTP_VIA')) $user_ip = getenv('HTTP_VIA');
        elseif(getenv('HTTP_XROXY_CONNECTION')) $user_ip = getenv('HTTP_XROXY_CONNECTION');
        elseif(getenv('HTTP_CLIENT_IP')) $user_ip = getenv('HTTP_CLIENT_IP');
        else return 'unknown';
        $user_ip = trim($user_ip);
        if(strlen($user_ip) > 15){
            $ar = split (', ', $user_ip);
            $ar_size = sizeof($ar)-1;
            for ($i= $ar_size; $i> 0; $i--){
                if($ar[$i]!='' and !preg_match('|[^\d\.]|', $ar[$i])){
                    $user_ip = $ar[$i];
                    break; 
                }
                if($i== sizeof($ar)-1) $user_ip = 'unknown';
            }
        }
        if(preg_match('|[^\d\.]|', $user_ip)) return 'unknown';
        if($forwarded) self::$forwarded_user_ip = $user_ip;
        else self::$remote_user_ip = $user_ip;
        return $user_ip;
    }
}    
?>