<?php
define("DEBUG_MODE", true); // режим отладки
// позгрузка конфигурации (там же подгружаются и инициируются необходимые части framework)
if(!require("config.php")) die("Unable to load config file!");
// индикатор режима асинхронного запроса (ajax)
$ajax_mode = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// использование класса работы с валютными курсами
require_once(LIB_PATH.'/class.currency.adapter.php');
$valutes = new CurrencyAdapter();

if($ajax_mode){
    $ajax_result = array('ok'=>true);
    $post_parameters = Request::getParameters(METHOD_POST);
    if(!isset($post_parameters['action'])) $post_parameters['action'] = '';
    // в случае невозможности получения данных о курсах, информируем об этом в штатном режиме
    if(!$valutes->Load()) $ajax_result = array('ok' => false, 'error'=>'Не удалось загрузить курсы валют');
    else {
        switch($post_parameters['action']){
            case 'list': // список курсов для выбранных валют
                $template = 'courses.list.html';
                $selected_courses = $valutes->getSelectedValutes();
                Response::setArray('selected_courses', $selected_courses);
                break;
            case 'add': // добавление валюты к списку отображаемых
                $valutes->addSelected($post_parameters['abbr']);
                break;
            case 'remove': // удаление валюты из списка отображаемых
                $valutes->removeSelected($post_parameters['abbr']);
                break;
            default: // в случае неопознанного или не указанного действия, сообщаем об ошибке в штатном режиме
                $ajax_result['ok'] = false;
                $ajax_result['error'] = 'Ошибка выбора действия';
        }
    }
    // если требуется форматирование данных для отображения, используем шаблонизатор для получения готового HTML
    if($ajax_result['ok'] && !empty($template)){
        $tpl = new Template($template);
        $html = $tpl->Processing(CACHE_TYPE_DYNAMIC, false);
        $ajax_result['html'] = $html;
    }
    // выдача результатов браузеру
    header("Content-type: application/json; charset=utf-8");
    echo Convert::json_encode($ajax_result);
    exit(0);
} else {
    // в режиме обычного запроса сайт просто отображает основную страницу
    if($valutes->Load()){
        // полный список доступных валют (для выбора пользователем)
        Response::setArray('valutes', $valutes->list);
    }
    // готовим и отображаем визуальное представление страницы
    $tpl = new Template('page.html');
    $tpl->Processing();
}
?>