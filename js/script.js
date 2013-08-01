/* интервал автоматического обновления данных (кол-во милисекунд) , по условиям задания - 24 часа */
var data_refresh_interval = 24*60*60*1000;
/* идентификатор таймера обновления списка */ 
var timeout_id = false;

jQuery(document).ready(function(){
    /* после загрузки страницы подгружаем данные о курсах валют */
    refreshList();
    /* обновление курсов при нажатии на кнопку ручного обновления */
    jQuery('.left_side .courses').on('click', '.btn-refresh', function(){
        refreshList();
        return false;
    });
    /* удаление указанной валюты из списка отображения */
    jQuery('.left_side .courses').on('click', '.btn-remove', function(){
        removeValute(jQuery(this).attr('data-abbr'));
        return false;
    });
    /* добавление выбранной валюты в список отображения */
    jQuery('.valute_add').on('submit', function(){
        addValute(jQuery('select[name="currency"]', jQuery(this)).val());
        return false;
    });
});

/**
* получение курсов валют в виде набора из выбранных пользователем валют
* (при каждом вызове функции обновляется таймер до следующего вызова)
*/
function refreshList(){
    /* показываем пользователю, что идет процесс обновления данных */
    jQuery(".left_side .courses").css('visibility', 'hidden');
    jQuery(".left_side .waitbox-list-load").show();
    /* запуск асинхронного обновления данных */
    jQuery.ajax({
        type: "POST", async: true,
        dataType: 'json', cache: false,
        url: window.location.href,
        data: {action: 'list'},
        success: function(msg){
            /* данные считаем корректными, если получили объект, в котором указано, что нет ошибок и есть сами данные (в виде готового html) */
            if( typeof(msg)=='object' && typeof(msg.ok)!='undefined' && msg.ok && typeof(msg.html)=='string' && msg.html.length) {
                jQuery('.left_side .courses').html(msg.html);
            } else {
                if(typeof(msg)=='object' && typeof(msg.error)!='undefined' && msg.error.length) alert('Ошибка: '+msg.error);
                else alert('Ошибка!');
            }
        },
        error: function(XMLHttpRequest, textStatus, errorThrown){
            alert('Ошибка получения данных с сервера.');
        },
        complete: function(){
            /* по завершении обмена убираем индикатор загрузки и показываем список */
            jQuery(".left_side .waitbox-list-load").hide();
            jQuery(".left_side .courses").css('visibility', 'visible');
        }
    });
    /* настраиваем следующий запуск обновления данных согласно указанному интервалу */
    if(timeout_id) clearTimeout(timeout_id);
    timeout_id = setTimeout(refreshList, data_refresh_interval);
}

/**
* Добавление валюты в список для отображения
* @param string аббревиатура валюты
*/
function addValute(_abbr){
    jQuery.ajax({
        type: "POST", async: true,
        dataType: 'json', cache: false,
        url: window.location.href,
        data: {action: 'add', abbr: _abbr},
        success: function(msg){
            if( typeof(msg)=='object' && typeof(msg.ok)!='undefined' && msg.ok) {
                refreshList();
            } else {
                if(typeof(msg)=='object' && typeof(msg.error)!='undefined' && msg.error.length) alert('Ошибка: '+msg.error);
                else alert('Ошибка!');
            }
        },
        error: function(XMLHttpRequest, textStatus, errorThrown){
            alert('Ошибка получения данных с сервера.');
        }
    });
}

/**
* Удаление валюты из списка для отображения
* @param string аббревиатура валюты
*/
function removeValute(_abbr){
    jQuery.ajax({
        type: "POST", async: true,
        dataType: 'json', cache: false,
        url: window.location.href,
        data: {action: 'remove', abbr: _abbr},
        success: function(msg){
            if( typeof(msg)=='object' && typeof(msg.ok)!='undefined' && msg.ok) {
                refreshList();
            } else {
                if(typeof(msg)=='object' && typeof(msg.error)!='undefined' && msg.error.length) alert('Ошибка: '+msg.error);
                else alert('Ошибка!');
            }
        },
        error: function(XMLHttpRequest, textStatus, errorThrown){
            alert('Ошибка получения данных с сервера.');
        }
    });
}