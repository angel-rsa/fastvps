<?php
if(!defined("CACHE_PATH")) define( "CACHE_PATH", ROOT_PATH . "/cache" );
if(!defined("CACHE_PATH_DYNAMIC")) define( "CACHE_PATH_DYNAMIC", CACHE_PATH."/dynamic" );
if(!defined("CACHE_PATH_STATIC")) define( "CACHE_PATH_STATIC", CACHE_PATH."/static" );
if(!defined("CACHE_PATH_QUERY")) define( "CACHE_PATH_QUERY", CACHE_PATH."/query" );
if(!defined("CACHE_PATH_DATA")) define( "CACHE_PATH_DATA", CACHE_PATH."/data" );
if(!defined("CACHE_TYPE_DYNAMIC")) define( "CACHE_TYPE_DYNAMIC", 1 ); // безвременное кеширование строк
if(!defined("CACHE_TYPE_STATIC")) define( "CACHE_TYPE_STATIC", 2 ); // кешировеание строк на время
if(!defined("CACHE_TYPE_QUERY")) define( "CACHE_TYPE_QUERY", 3 ); // кеширование данных на время
if(!defined("CACHE_TYPE_DATA")) define( "CACHE_TYPE_DATA", 4 ); // безвременное кеширование данных

/**
* Cache - static class for pages/templates/queries caching
*/
class Cache{
    public static $query_cache_time = 30; // время кэширования по умолчанию (сек)
    public static $static_cache_time = 3600; // время кэширования по умолчанию (сек)
    
    
    public static function init($query_cache_time=30, $static_cache_time=3600){
        self::$query_cache_time = $query_cache_time;
        self::$static_cache_time = $static_cache_time;
    }
    /**
    * Write data to cache
    * @param string signature of object (for hash calculate)
    * @param mixed data to write
    * @param int cache type
    * @return bool
    */
    public static function Write(&$signature, &$data, $cacheType){
        $res = false;
        $buffer = null;
        if(is_array($data)) {
            $buffer = $data;
            $data = addslashes(serialize($data));
        }
        $hash = self::GetHach($signature);
        $filename = self::GetCacheFilePath($hash, $cacheType);
        if(self::makeDir($filename)) {
            if(function_exists("file_put_contents")){
                $res = file_put_contents($filename, $data);
            } else {
                $fh = fopen($filename, "w");
                if($fh){
                    $res = fwrite($fh, $data);
                    if($res!==false) if(!fclose($fh)) $res = false;
                } else $res = false;
            }
            if($res!==false) {
                chmod( $filename, 0666 );
            }
        }
        if($buffer!==null) {
            $data = $buffer;
            unset($buffer);
        }
        return $res!==false ? $filename : $res;
    }

    /**
    * Read data from cache
    * @param string signature of object (for hash calculate)
    * @param int cache type
    * @param int time of cache actuality (sec)
    * @return mixed data or null
    */
    public static function Read(&$signature, $cacheType, $cacheTime=null){
        $hash = self::GetHach($signature);
        $file = self::GetCacheFilePath($hash, $cacheType);
        if(!is_file($file)) return null;
        if($cacheType == CACHE_TYPE_STATIC || $cacheType == CACHE_TYPE_QUERY) {
            if(is_null($cacheTime)) $cacheTime = $cacheType==CACHE_TYPE_STATIC ? self::$static_cache_time : self::$query_cache_time;
        }
        if(!empty($cacheTime)){
            $time = filemtime($file);
            if( time()-$time > $cacheTime ) return null;
        }
        if(function_exists("file_get_contents")){
            $data = file_get_contents($file);
        } else {
            $data = null;
            if($fh = fopen($file, "r")){
                $data = fread($fh, filesize($file));
                fclose($fh);
            }
        }
        if($cacheType==CACHE_TYPE_QUERY || CACHE_TYPE_DATA) $data = unserialize(stripslashes($data));
        return $data;
    }

    /**
     * Clear all cached files and dirs
     * @param mixed type of cache
     */
    public static function Clear( $cacheType = null ) {
        if( $cacheType == CACHE_TYPE_DYNAMIC ) {
            $path = CACHE_PATH_DYNAMIC;
        } elseif( $cacheType == CACHE_TYPE_STATIC ) {
            $path = CACHE_PATH_STATIC;
        } elseif( $cacheType == CACHE_TYPE_QUERY ) {
            $path = CACHE_PATH_QUERY;
        } elseif( $cacheType == CACHE_TYPE_DATA ) {
            $path = CACHE_PATH_DATA;
        } elseif( empty( $cacheType ) ) {
            self::Clear( CACHE_TYPE_DYNAMIC );
            self::Clear( CACHE_TYPE_STATIC );
            self::Clear( CACHE_TYPE_QUERY );
            self::Clear( CACHE_TYPE_DATA );
            self::rClear( CACHE_PATH, false, false );
            return null;
        } else {
            return null;
        }
        self::rClear($path);
    }
    protected static function rClear($path, $deleteFolders = true, $recursive = true) {
        if( is_dir( $path ) ) {
            if( $dir = opendir( $path ) ) {
                while( ($element = readdir( $dir )) !== false ) {
                    if($element != "." && $element != ".." && is_dir( $path . "/" . $element )){
                        if($recursive) self::rClear($path.'/'.$element, $deleteFolders, $recursive);
                        if($deleteFolders) rmdir( $path . "/" . $element );
                    } elseif($element != "." && $element != ".." && is_file( $path . "/" . $element )) unlink( $path . "/" . $element );
                }
                closedir( $dir );
            }
        }
    }

    /**
    * Get hash for data
    * @param mixed $data
    * @return string
    */
    public static function GetHach(&$data){
        return sha1(addslashes(serialize($data)));
    }
    /**
    * Get path to cached file
    * @param string hash string
    * @param integer cache type
    * @return string path to file
    */
    private static function GetCacheFilePath( $hash, $cacheType ) {
        switch($cacheType){
            case CACHE_TYPE_STATIC:
                $path = CACHE_PATH_STATIC . "/" . substr($hash,0,1) . "/" . $hash . ".cache";
                break;
            case CACHE_TYPE_QUERY:
                $path = CACHE_PATH_QUERY . "/" . substr($hash,0,2) . "/" . $hash . ".cache";
                break;
            case CACHE_TYPE_DYNAMIC:
                $path = CACHE_PATH_DYNAMIC . "/" . substr($hash,0,2) . "/" . $hash . ".cache";
                break;
            case CACHE_TYPE_DATA:
                $path = CACHE_PATH_DATA . "/" . substr($hash,0,1) . "/" . $hash . ".cache";
                break;
        }
        return $path;
    }
    /**
    * Check for folder is exists and create it recursively if it need
    * @param string $path path to the file
    * @return boolean
    */
    private static function makeDir($path){
        if(empty($path)) return false;
        $dir = dirname($path);
        if(is_dir($dir)) return true;
        $result = true;
        if(!mkdir($dir, 0777, true)) return false;
        else chmod($dir, 0777);
        return true;
    }
    /**
     * Checks Cached HTML or Cached Query path
     * @param string $path path to template
     * @param integer $cacheType cache type
     * @param integer $cacheTime time while cache file will Alive
     * @return mixed path to cached html file or false if file not found or expired
     */
    public static function GetCachedPath( &$cacheString, $cacheType, $cacheTime = 0 ) {
        $path = self::GetCacheFilePath( self::GetHach($cacheString), $cacheType );
        if(!is_file($path)) return false;
        if($cacheType == CACHE_TYPE_STATIC || $cacheType == CACHE_TYPE_QUERY) {
            $time = filemtime($path);
            if( $cacheTime < time() - $time ) return false;
        }
        return $path;
    }
}
?>
