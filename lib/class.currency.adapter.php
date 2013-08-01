<?php
require_once(LIB_PATH."/class.cache.php");

/**
* Класс для работы с курсами валют
* - загрузка курсов из внешнего источника (официальный сайт ЦБР)
* - хранение и получение курса по аббревиатуре валюты
* - кеширование загруженных курсов
*/
class CurrencyAdapter {
    public $list = array();
    
    /**
    * Загрузка курсов из кеша или из внешнего источника (если кеш устарел или данные устарели)
    * @return boolean признак успешности загрузки данных по курсам
    */
    public function Load() {
        $date_now = date('d.m.Y');  // работаем с курсами на текущую дату
        $cache_sig = Host::$host.'::currency_xml_data'; // сигнатура для обращения к закешированным данным
        $cached_data = Cache::Read($cache_sig, CACHE_TYPE_DATA, 60*60*24); // попытка получить кешированные данные
        // если не было кешированных данных, либо они устарели (дата сменилась), то производим загрузку из внешнего источника и запись свежего кеша
        if(empty($cached_data) || $date_now!=$cached_data['date']){
            $url = 'http://www.cbr.ru/scripts/XML_daily.asp?date_req='.$date_now;
            $xml = new DOMDocument();
            // попытка загрузить xml из внешнего источника
            if($xml->load($url)) {
                // если загрузили данные - разбираем xml
                $this->list = array(); 
                $root = $xml->documentElement;
                $items = $root->getElementsByTagName('Valute');
                foreach ($items as $item) {
                    $code = $item->getElementsByTagName('CharCode')->item(0)->nodeValue;
                    $value = $item->getElementsByTagName('Value')->item(0)->nodeValue;
                    $title = $item->getElementsByTagName('Name')->item(0)->nodeValue;
                    $nominal = $item->getElementsByTagName('Nominal')->item(0)->nodeValue;
                    $this->list[$code] = array(
                        'code' => $code,
                        'value' => floatval(str_replace(',', '.', $value)),
                        'title' => $title,
                        'nominal' => intval($nominal)
                    );
                }
                // кешируем новые данные
                $cached_data = array('date'=>$date_now, 'list'=>$this->list);
                Cache::Write($cache_sig, $cached_data, CACHE_TYPE_DATA);
            } else return false;
        } else {
            // если данные получены из кеша - переносим список валют в соответствующее свойство объекта
            $this->list = $cached_data['list'];
        }
        return true;
    }
 
    /**
    * получение курса валюты по его аббревиатуре
    * @param string аббревиатура валюты (сокращение, принятое в ЦБР)
    * @return float курс заданной валюты к рублю
    */
    public function getValute($abbr) {
        return isset($this->list[$abbr]) ? $this->list[$abbr] : array();
    }
    
    /**
    * получение списка аббревиатур выбраных для показа валют
    * @return array аббревиатуры валют                
    */
    public function getSelected(){
        $sig = Host::$host.'::selected_valutes_list';
        $selected = Cache::Read($sig, CACHE_TYPE_DATA);
        if(is_null($selected)) {
            $selected = array('USD', 'EUR', 'BYR', 'UAH');  // значения по умолчанию
            $this->setSelected($selected, $sig);
        }
        return $selected;
    }

    /**
    * Запись списка аббревиатур выбранных для показа валют
    * @param array аббревиатуры
    * @param mixed сигнатура кэша
    */
    public function setSelected($selected, $sig=null){
        if(empty($sig)) $sig = Host::$host.'::selected_valutes_list';
        Cache::Write($sig, $selected, CACHE_TYPE_DATA);
    }
    
    /**
    * добавление аббревиатуры к списку выбранных для показа валют
    * @param string аббревиатура
    */
    public function addSelected($abbr){
        $selected = $this->getSelected();
        if(in_array($abbr, array_keys($this->list))){
            if(!in_array($abbr, $selected)) {
                $selected[] = $abbr;
                $this->setSelected($selected);
            }
        }
    }

    /**
    * Удаление аббревиатуры из списка выбранных для показа валют
    * @param string аббревиатура
    */
    public function removeSelected($abbr){
        $selected = $this->getSelected();
        $key = array_search($abbr, $selected);
        if($key!==false) {
            unset($selected[$key]);
            $this->setSelected($selected);
        }
    }
    
    /**
    * Получение набора данных по выбранным для показа валютам
    * @return array
    */
    public function getSelectedValutes(){
        $selected = $this->getSelected();
        // выбрать список данных по выбранным валютам
        $selected_courses = array();
        foreach($selected as $abbr){
            $selected_courses[$abbr] = $this->getValute($abbr);
        }
        return $selected_courses;
    }
}

?>