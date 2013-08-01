<?php
/**
* ----------------------------------------------------------------------------------------------------------------------
* Class Templates
* before use init it by function init($filename);
* ----------------------------------------------------------------------------------------------------------------------
*/
require_once(LIB_PATH.'/class.storage.php');
require_once(LIB_PATH.'/class.cache.php');

class Template {

    public $template = '';
    private $recursive_counter = 0;
    private $tpl_compact = false;

    public function __construct($template_filename, $counter=0) {
        $this->recursive_counter = $counter;
        $this->template = $this->Load( $template_filename );
        if(defined("TPL_COMPACT") && TPL_COMPACT==1) $this->tpl_compact = true;
    }
    

    public function Processing($cacheType=CACHE_TYPE_DYNAMIC, $show_in_browser=true, $uri=null){
        if($this->recursive_counter>15) {
            $this->template = "recursive overflow in templates";
        }
        $this->Combine($cacheType);
        $php_path = Cache::GetCachedPath($this->template, CACHE_TYPE_DYNAMIC);
        if( empty( $php_path ) ) {
            $php_template = $this->CreatePHP($this->template);
            $php_path = Cache::Write($this->template, $php_template, CACHE_TYPE_DYNAMIC);
        }
        $html_contents = $this->CreateHTML($php_path,$show_in_browser);
        if($cacheType == CACHE_TYPE_STATIC) {
            if(empty($uri)) $uri = Host::$requested_uri;
            $url = Host::GetWebPath($uri);
            Cache::Write( $url, $html_contents, CACHE_TYPE_STATIC );
        }
        if(!$show_in_browser) return $html_contents;
    }
    
    /**
    * Compress the text by removing duplicate blank characters
    * @param string $tpl tpl contents
    * @return string
    */
    public function CompressTPL($tpl){
        $tpl = preg_replace("|[\s\t\n\r]+|umsi", ' ', $tpl);
        $tpl = preg_replace("|>[\s\t\n\r]+<|umsi", "><", $tpl);
        return $tpl;
    }

    /**
     * Creates (and show) html from php-notation file
     * @param string filename with template php-notation
     * @param boolean show in browser
     * @return string content of HTML
     */
    public function CreateHTML($php_file_path, $show_in_browser=true) {
        // подготовка переданных параметров
        $params = Request::GetParameters();
        foreach( $params as $key => $value ) {
            $$key = $value;
        }
        // включение буферизации
        ob_start();
        include($php_file_path);
        $buffer = ob_get_contents();
        ob_end_clean();
        if(empty($showpage['adminpanel'])){
            // раскрытие внутренних вызовов (блоки контента)
            $mcount = preg_match_all( '!{(block|page):\s?([^}\s]+)\s*\}!i', $buffer, $matches, PREG_SET_ORDER);
            if($mcount){
                $backup_env_params = $params; // сохраняем все текущие параметры (могут быть перезаписаны внутри блока)
                foreach($matches as $match){
                    $pg = new Page($match[2]);
                    $cont = $pg->Render(false);
                    $buffer = str_replace( $match[0], $cont, $buffer );
                }
                Response::SetParametersFromArray($backup_env_params); // восстанавливаем текущие параметры
            }
            // раскрытие внутренних подстановок констант и переменных
            $mcount = preg_match_all( '!{(\$*)([\w\_]+[\w\d\_]*)}!i', $buffer, $matches, PREG_SET_ORDER);
            if($mcount){
                foreach($matches as $match){
                    if(empty($match[2]))
                        $val = defined($match[2]) ? constant($match[2]) : "";
                    else
                        $val = isset($$match[2]) ? $$match[2] : "";
                    $buffer = str_replace( $match[0], $val, $buffer );
                }
            }
        }
        if($this->tpl_compact) $buffer = $this->CompressTPL( $buffer );
        if($show_in_browser) echo $buffer;
        return $buffer;
    }

    /**
     * @desc Load template file for working with it
     * @param string template filename (with path)
     * @return string contents of template
     */
    public function Load($filename) {
        if(file_exists($filename)) return file_get_contents($filename);
        $checkfile = TEMPLATES_PATH.'/'.trim($filename,'/');
        if(file_exists($checkfile)) return file_get_contents($checkfile);
        $checkfile = ROOT_PATH .'/'. trim($filename,'/');
        if(file_exists($checkfile)) return file_get_contents($checkfile);
        return false;
    }

    /**
     * @desc рыскрытие функций
     */
    private function DiscloseFunctions( $tpl ) {
        $tpl = preg_replace( '!{(web|root):\s*([^}\s]+)\s*}!i', '<?php echo Host::GetWebPath("\\2");?>', $tpl );
        $tpl = preg_replace( '!{(do|php):\s*([^}]+)}!i', '<?php \\2;?>', $tpl );
        $tpl = preg_replace( '!{(print|echo):\s*([^}]+)}!i', '<?php echo \\2?>', $tpl );
        $tpl = preg_replace( '!{(value|escape):\s*([^}]+)}!i', '<?php echo isset(\\2)?htmlentities(\\2,ENT_QUOTES,SITE_CHARSET):""?>', $tpl );
        $tpl = preg_replace( '!{(clean|strip):\s*([^}]+)}!i', '<?php echo isset(\\2)?htmlentities(strip_tags(\\2),ENT_QUOTES,SITE_CHARSET):""?>', $tpl );
        return $tpl;
    } 

    /**
     * @desc рыскрытие переменных
     */
    private function DiscloseVars( $tpl, $set_values = false ) {
        if($set_values) {
            // простая переменная или свойство класса
            $tpl = preg_replace( '|{(\$[\$\[\]\w\d\_\'\"(->)]+)}|i', '".(isset(\\1)?\\1:"")."', $tpl );
            // свойство статичного класса
            $tpl = preg_replace( '|{([\w\d\_]+::\$[\w\d\[\]\_\'\"]+)}|i', '".(isset(\\1)?\\1:"")."', $tpl );
            // константа
            $tpl = preg_replace( '|{([\w\_]+[\w\d\_]*)}|i', '".(defined("\\1")?\\1:"")."', $tpl );
            $result = $tpl;
            try {
                eval("\$tpl = \"$tpl\";");
            } catch (Exception $e) {
                $tpl = $result;
            }
        } else {
            // простая переменная или свойство класса
            $tpl = preg_replace( '|{(\$[\$\[\]\w\d\_\'\"(->)]+)}|i', '<?php echo isset(\\1)?\\1:""?>', $tpl );
            // свойство статичного класса
            $tpl = preg_replace( '|{([\w\d\_]+::\$[\w\d\[\]\_\'\"]+)}|i', '<?php echo isset(\\1)?\\1:""?>', $tpl );
            // константа
            $tpl = preg_replace( '|{([\w\_]+[\w\d\_]*)}|i', '<?php echo defined("\\1")?\\1:""?>', $tpl );
        }
        return $tpl;
    }

    /**
     * @desc раскрытие условного ветвления
     */
    private function DiscloseBranches( $tpl ) {
        $tpl = preg_replace( '|<else|i', '<}else', $tpl );
        $tpl = preg_replace( '|<(}else)?if\s+(!?)([^>]+)>|i', '<?php \\1if(\\2(\\3)){?>', $tpl );
        $tpl = preg_replace( '|<(}else)?if(!?)eq\s+([^\;\,]+)[\;\,]{1}([^>]+)>|i', '<?php \\1if(isset(\\3) && \\2(\\3 == \\4)){?>', $tpl );
        $tpl = preg_replace( '|<(}else)?if(!?)lt\s+([^\;\,]+)[\;\,]{1}([^>]+)>|i', '<?php \\1if(isset(\\3) && \\2(\\3 < \\4)){?>', $tpl );
        $tpl = preg_replace( '|<(}else)?if(!?)gt\s+([^\;\,]+)[\;\,]{1}([^>]+)>|i', '<?php \\1if(isset(\\3) && \\2(\\3 > \\4)){?>', $tpl );
        $tpl = preg_replace( '|<(}else)?if(!?)gt\s+([^\;\,]+)[\;\,]{1}([^>]+)>|i', '<?php \\1if(isset(\\3) && \\2(\\3 > \\4)){?>', $tpl );
        $tpl = preg_replace( '|<(}else)?if(!?)in\s+([^\;\,]+)[\;\,]{1}([^>]+)>|i', '<?php \\1if(isset(\\3) && \\2in_array(\\3, array(\\4))){?>', $tpl );
        $tpl = preg_replace( '|<(}else)?if(!?)empty\s+([^>]+)>|i', '<?php \\1if(\\2empty(\\3)){?>', $tpl );
        $tpl = preg_replace( '|<}else>|i', '<?php }else{ ?>', $tpl );
        $tpl = preg_replace( '|</if>|i', '<?php }?>', $tpl );
        return $tpl;
    }

    /**
     * @desc раскрытие циклов
     */
    private function DiscloseLoops( $tpl ) {
        $res = preg_match_all( '|<loop (\$[\$\[\]\w\d\_\'\"]+)[\;\,]{1}(\$[\w\d\_]+)[\;\,]{1}(\$[\w\d\_]+)>|i', $tpl, $matches, PREG_SET_ORDER );
        foreach( $matches as $match ) {
            $start = strpos( $tpl, $match[0] );
            if( $start !== false ) {
                $end = strpos( $tpl, '</loop ' . $match[1] . '>', $start );
                $block = substr( $tpl, $start + strlen( $match[0] ), $end - $start - strlen( $match[0] ) );
                $body = $this->CreatePHP( $block );
                $tpl = substr( $tpl, 0, $start ) . '<?php foreach(' . $match[1] . ' as ' . $match[2] . '=>' . $match[3] . '){ ?>' . $body . '<?php }?>' . substr( $tpl, $end + strlen( '</loop ' . $match[1] . '>' ) );
            }
        }
        return $tpl;
    }

    /**
     * @desc translate block of text from template in PHP-notation
     */
    private function CreatePHP( $tpl ) {
        // сохраняем и временно убираем свободные от преобразований зоны
        $count = preg_match_all('|[{<]literal[}>](.*)[{<]/literal[}>]|Umsi', $tpl, $matches, PREG_SET_ORDER);
        if($count) $tpl = preg_replace('|[{<]literal[}>](.*)[{<]/literal[}>]|Umsi','<literal></literal>', $tpl);
        // Раскрываем встроенные шаблонные функции управления выводом
        $tpl = $this->DiscloseFunctions($tpl);
        // Преобразуем циклы в шаблоне
        $tpl = $this->DiscloseLoops($tpl);
        // Преобразуем ветвление в шаблоне
        $tpl = $this->DiscloseBranches($tpl);
        // Раскрываем переменные и константы
        $tpl = $this->DiscloseVars($tpl);
        // восстанавливаем свободную от преобразований зону
        foreach($matches as $match){
            $pos = strpos($tpl, '<literal></literal>');
            $tpl = substr($tpl, 0, $pos).$match[1].substr($tpl, $pos+19);
        }
        return $tpl;
    }

    /**
     * @desc combine all templates in one
     */
    public function Combine($cacheType) {
        do {
            $combined = 0;
            $pos = strpos( $this->template, "<include " );
            if( $pos !== false ) {
                $endpos = strpos( $this->template, ">", $pos + 9 );
                $filename = substr( $this->template, $pos + 9, $endpos - $pos - 9 );
                $combined++;
                // замена констант и переменных в имени подключаемого файла
                $newfilename = $this->DiscloseVars($filename, true);
                $inc_tpl = new Template($newfilename, $this->recursive_counter+1);
                $inc_content = $inc_tpl->Processing($cacheType, false);
                $this->template = str_replace("<include $filename>", $inc_content, $this->template);
                unset($inc_content);
                unset($inc_tpl);
            }
        }
        while($combined>0);
        return true;
    }
}
?>
