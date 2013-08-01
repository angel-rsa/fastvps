<?php
require_once(LIB_PATH."/class.convert.php");

if(!defined("METHOD_POST")) define( "METHOD_POST", "post" );
if(!defined("METHOD_GET")) define( "METHOD_GET", "get" );
if(!defined("METHOD_REQUEST")) define( "METHOD_REQUEST", "request" );

/**
* Storage abstract class
*/
abstract class Storage {
    protected static $params = array();
    protected static $get = array();
    protected static $post = array();
    protected static $initialized = false;

    protected static function get($key, $method, $type){
        if( $method == METHOD_GET ) {
            if( isset( self::$get[$key] ) ) return Convert::ToValue( self::$get[$key], $type );
        } elseif( $method == METHOD_POST ) {
            if( isset( self::$post[$key] ) ) return Convert::ToValue( self::$post[$key], $type );
        } else {
            if( isset( self::$params[$key] ) ) return Convert::ToValue( self::$params[$key], $type );
        }
        return null;
    }

    protected static function set($key, $value, $method, $type){
        $value = Convert::ToValue( $value, $type );
        if( $method == METHOD_GET ) {
            self::$get[$key] = $value;
        } elseif( $method == METHOD_POST ) {
            self::$post[$key] = $value;
        } else {
            self::$params[$key] = $value;
        }
    }
}

/**
* Request Class
*/
class Request extends Storage {

    public static function Init() {
        parent::$get = $_GET;
        parent::$post = $_POST;
        parent::$params = array_merge( $_GET, $_POST );
        parent::$initialized = true;
        Response::Init();
    }

    // --- Getters --- //
    public static function getInteger( $key, $method = METHOD_REQUEST ) {
        return parent::get( $key, $method, TYPE_INTEGER );
    }

    public static function getBoolean( $key, $method = METHOD_REQUEST ) {
        return parent::get( $key, $method, TYPE_BOOLEAN );
    }

    public static function getString( $key, $method = METHOD_REQUEST ) {
        return parent::get( $key, $method, TYPE_STRING );
    }

    public static function getFloat( $key, $method = METHOD_REQUEST ) {
        return parent::get( $key, $method, TYPE_FLOAT );
    }

    public static function getArray( $key, $method = METHOD_REQUEST ) {
        return parent::get( $key, $method, TYPE_ARRAY );
    }

    public static function getObject( $key, $method = METHOD_REQUEST ) {
        return parent::get( $key, $method, TYPE_OBJECT );
    }

    public static function getParameter( $key, $method = METHOD_REQUEST ) {
        return parent::get( $key, $method, TYPE_PARAMETER );
    }

    public static function getValue( $key, $type, $method = METHOD_REQUEST ) {
        return parent::get( $key, $method, $type );
    }

    public static function getParameters( $method = METHOD_REQUEST ) {
        if( $method == METHOD_POST ) {
            return parent::$post;
        } elseif( $method == METHOD_GET ) {
            return parent::$get;
        } else {
            return parent::$params;
        }
    }
}

/**
* Response Class
*/
class Response extends Storage {

    public static function Init() {
        parent::$initialized = true;
    }

    // --- Setters --- //
    public static function setInteger( $key, $value, $method = null ) {
        return parent::set( $key, $value, $method, TYPE_INTEGER );
    }

    public static function setBoolean( $key, $value, $method = null ) {
        return parent::set( $key, $value, $method, TYPE_BOOLEAN );
    }

    public static function setString( $key, $value, $method = null ) {
        return parent::set( $key, $value, $method, TYPE_STRING );
    }

    public static function setFloat( $key, $value, $method = null ) {
        return parent::set( $key, $value, $method, TYPE_FLOAT );
    }

    public static function setArray( $key, $value, $method = null ) {
        return parent::set( $key, $value, $method, TYPE_ARRAY );
    }

    public static function setObject( $key, $value, $method = null ) {
        return parent::set( $key, $value, $method, TYPE_OBJECT );
    }

    public static function setParameter( $key, $value, $method = null ) {
        return parent::set( $key, $value, $method, TYPE_PARAMETER );
    }

    public static function setParametersFromArray( $array, $method = null ) {
        foreach( $array as $key => $value ) {
            self::SetParameter( $key, $value, $method );
        }
    }
}

/**
* ----------------------------------------------------------------------------------------------------------------------
* Super global _COOKIE wrapper
* ----------------------------------------------------------------------------------------------------------------------
*/
class Cookie {

    private static $initialized = false;
    public static function Init() {
        self::$initialized = true;
    }

    private static function value( $key, $type ) {
        if( !self::$initialized ) {
            return null;
        }
        if( !empty( $_COOKIE[$key] ) ) {
            if( $type == TYPE_ARRAY ) {
                return unserialize( stripcslashes($_COOKIE[$key]) );
            }
            return Convert::toValue( $_COOKIE[$key], $type );
        }
        return null;
    }

    /**
    * Send a cookie
    * @param string  $name     The name of the cookie
    * @param string  $value    The value of the cookie. This value is stored on the clients computer; do not store sensitive information.
    * @param int     $expires  The time the cookie expires. time()+60*60*24*30 will set the cookie to expire in 30 days. If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes).
    * @param string  $path     The path on the server in which the cookie will be available on. If set to '/', the cookie will be available within the entire domain.
    * @param string  $domain   The domain that the cookie is available. To make the cookie available on all subdomains of example.com then you'd set it to '.example.com'. The . is not required but makes it compatible with more browsers. Setting it to www.example.com will make the cookie only available in the www subdomain.
    * @param bool    $secure   Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client. When set to TRUE, the cookie will only be set if a secure connection exists. The default is FALSE. On the server-side, it's on the programmer to send this kind of cookie only on secure connection (e.g. with respect to $_SERVER["HTTPS"]).
    * @param bool    $httponly When TRUE the cookie will be made accessible only through the HTTP protocol. This means that the cookie won't be accessible by scripting languages, such as JavaScript. This setting can effectly help to reduce identity theft through XSS attacks (although it is not supported by all browsers). Added in PHP 5.2.0.
    * @return bool
    */
    public static function setCookie( $name, $value = null, $expires = null, $path = null, $domain = null, $secure = false, $httponly = false ) {
        $file = null;
        $line = null;
        if( is_array( $value ) ) {
            $value = serialize( $value );
            if(!get_magic_quotes_gpc()) $value = addslashes($value);
        } else {       
            $value = Convert::ToString( $value );
        }
        if( headers_sent( $file, $line ) ) {
            return false;
        }
        return setcookie( $name, $value, $expires, $path, $domain, $secure, $httponly );
    }

    // --- Getters --- //
    public static function getInteger( $key ) {
        return self::value( $key, TYPE_INTEGER );
    }

    public static function getBoolean( $key ) {
        return self::value( $key, TYPE_BOOLEAN );
    }

    public static function getString( $key ) {
        return self::value( $key, TYPE_STRING );
    }

    public static function getFloat( $key ) {
        return self::value( $key, TYPE_FLOAT );
    }

    public static function getArray( $key ) {
        return self::value( $key, TYPE_ARRAY );
    }

    public static function getParameter( $key ) {
        return self::value( $key, TYPE_PARAMETER );
    }
}

/**
* Super global _SESSION wrapper
*/
class Session {
    private static $initialized = false;

    /**
     * Initialize session
     */
    public static function Init($minutes=null,$name=null,$cache_limiter='nocache') {
        if(is_null($minutes)) $minutes = 1440; // 1440 == 24 часа
        if(!empty($name)) self::SetName($name);
        if(!in_array($cache_limiter,array('public','private','nocache','private_no_expire'))) $cache_limiter = 'public';
        if( self::$initialized ) self::Destroy();
        session_cache_expire($minutes); 
        session_cache_limiter($cache_limiter);
        self::$initialized = session_start();
    }

    /**
    * Main setter method
    * @param string $key
    * @param mixed $value
    * @param int $type
    */
    private static function set( $key, $value, $type ) {
        if( !self::$initialized ) {
            return null;
        }
        $value = Convert::ToValue( $value, $type );
        $_SESSION[$key] = $value;
        return null;
    }

    /**
    * Main getter method
    * @param string $key
    * @param int $type
    */
    private static function get( $key, $type ) {
        if( !self::$initialized ) {
            return null;
        }
        if(isset($_SESSION[$key])) return Convert::ToValue($_SESSION[$key],$type);
        return null;
    }

    /**
     * Get session id
     * @return string
     */
    public static function getId() {
        return session_id();
    }

    /**
     * Set session id
     * @param string $id
     * @return string
     */
    public static function setId( $id ) {
        return session_id( $id );
    }

    /**
     * Get session name
     * @return string
     */
    public static function getName() {
        return session_name();
    }

    /**
     * Destroy Session
     * @return bool
     */
    public static function Destroy() {
        self::$initialized = false;
        return session_destroy();
    }

    /**
     * Get Session Save Path
     * @return string
     */
    public static function getSavePath() {
        return session_save_path();
    }

    /**
     * Update the current session id with a newly generated one
     * @param bool $deleteOldSession
     * @return bool
     */
    public static function regenerateId( $deleteOldSession = false ) {
        return session_regenerate_id( $deleteOldSession );
    }

    /**
     * Set session name
     * @param string $name
     * @return string
     */
    public static function setName( $name ) {
        return session_name( $name );
    }

    // --- Getters --- //
    public static function getInteger( $key ) {
        return self::get( $key, TYPE_INTEGER );
    }

    public static function getBoolean( $key ) {
        return self::get( $key, TYPE_BOOLEAN );
    }

    public static function getString( $key ) {
        return self::get( $key, TYPE_STRING );
    }

    public static function getFloat( $key ) {
        return self::get( $key, TYPE_FLOAT );
    }

    public static function getArray( $key ) {
        return self::get( $key, TYPE_ARRAY );
    }

    public static function getObject( $key ) {
        return self::get( $key, TYPE_OBJECT );
    }

    public static function getParameter( $key ) {
        return self::get( $key, TYPE_PARAMETER );
    }

    // --- Settegs --- //
    public static function setInteger( $key, $value ) {
        return self::set( $key, $value, TYPE_INTEGER );
    }

    public static function setBoolean( $key, $value ) {
        return self::set( $key, $value, TYPE_BOOLEAN );
    }

    public static function setString( $key, $value ) {
        return self::set( $key, $value, TYPE_STRING );
    }

    public static function setFloat( $key, $value ) {
        return self::set( $key, $value, TYPE_FLOAT );
    }

    public static function setArray( $key, $value ) {
        return self::set( $key, $value, TYPE_ARRAY );
    }

    public static function setObject( $key, $value ) {
        return self::set( $key, $value, TYPE_OBJECT );
    }

    public static function setParameter( $key, $value ) {
        return self::set( $key, $value, TYPE_PARAMETER );
    }

    /**
     * Unregisters key
     * @param string $key
     * @return bool
     */
    public static function unsetParameter( $key ) {
        return session_unregister( $key );
    }
}
?>