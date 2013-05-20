<?php
//Тыдыщ изменение
//Тыдыщ изменение 2
/********************************************************************************
	Обработчики
*********************************************************************************/
//Стандартная функция для автоматического подключения модулей классов
spl_autoload_register(function ($name){
//e(__FUNCTION__ . ':' . $name);
	if (substr($name, 0, 5) == 'Core\\'){//Это класс ядра
		$filename = substr($name, 5);
		$filename = CORE_DIR . 'php/' . strtolower(str_replace('\\','/',substr($name, 5))).'.php';
//e('Класс ядра: ' . $filename);
		require($filename);
	}else{
		$filename = PHP_DIR . strtolower(str_replace('\\','/',$name)).'.php';
//e('Класс разработчика: ' . $filename);
		require($filename);
	}

});


register_shutdown_function(function (){
     $error=error_get_last();
     if ($error['type']==1) {// фатальная ошибка
         // действие
		 echo('Это фатальная ошибка!');
     }

	//e(__FUNCTION__);
	//app()->database->disconnect();
});

function app(){
	global $APP;
	return $APP;
}

/********************************************************************************
		Работа с пользователями
*********************************************************************************/
//Проверка авторизации
function validUser(){
	return isset(app()->sysSession['user']);
}

//Текущий пользователь
function currentUser(){
	$sess = app()->sysSession;
	return isset($sess['user']) ? $sess['user'] : NULL;
}

//Текущий владелец приложения
function currentOwner(){
	$sess = app()->sysSession;
	return isset($sess['owner']) ? $sess['owner'] : NULL;
}

/********************************************************************************
		Функции для отладки
*********************************************************************************/

//в переданном массиве все объекты заменяет на их строковые представления
function resetObjects($array){

	if (is_array($array)){
		$res = array();
//e('колбасим массив');
		foreach($array as $key => $value){
//e('поле: ' . $key . ', тип=' . gettype($value));
			if (is_object($value)){
				$res[$key] = resetObjects($value);
//e('значение - объект');
//e($array[$key]);
			}elseif (is_array($value)){
				//unset($array[$key]);
				$res[$key] = resetObjects($value);
//e('значение - массив');
//e($array[$key]);
			}elseif (is_null($value)) {
				//$v = NULL;
				//unset($array[$key]);
				$res[$key] = "<NULL>";
			}else{
				$res[$key] = $value;
			}
		}
		
		$array = $res;
	}elseif (is_object($array)){
		if (get_class($array)=='stdClass'){
			//выдадим массив полей станд. класса
			return '['.get_class($array).':'.e(resetObjects((array) $array), true).']';;
		}else{
			$v = '['.get_class($array).':'.strval($array).']';
			unset($array);
			$array = $v;
		}
		
	}elseif (is_null($array)){
		unset($array);
		$array = "<NULL>";
	}

	return $array;
}//resetObjects

//$return задает режим, что нужно вернуть значение, но не выводить на экран
function e($value, $return = false){ //Отладочная функция, выводит красиво на экран значения

	$res = '';
	if (!CONSOLE_MODE) $res .= '<pre><code>';
	$v = $value;
	$v = resetObjects($v);
	$res .= print_r($v, true);
	if (CONSOLE_MODE) $res .= PHP_EOL;
	else $res .= '</code></pre>';
	
	if (!$return) echo $res;

	return $res;
}

function debug($msg){
	if (DEBUG_MODE)	{
		e($msg);
	}
}



function filelog($msg){
	if (ENABLE_LOG)	{
		//Будем писать в системный лог последние сообщения
		$logname = APP_DIR.'log.htm';
		$log_exists = file_exists($logname);
		$flags = LOCK_EX;

		if (!$log_exists){ 

			$log_msg = '<head>
				<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
				<meta http-equiv="cache-control" content="no-cache">
				</head>
				'.

				$msg;//файл будет расти, поэтому никаких тегов body и т.п.

		}else{

			//узнаем размер файла, если больше чем положено (не больше 100Кб), обнулим
			//if (filesize($logname) <= 1048576) $flags |= FILE_APPEND;
			if (filesize($logname) <= MAX_LOG_SIZE) $flags |= FILE_APPEND;

			$log_msg = $msg;

		}



		file_put_contents(
			$logname, 
				'<div style="padding:10px; border:1px dashed #ccc;"><div style="background:lightblue">'.currentDate().'</div>'
				.$log_msg
				.'</div>',
			$flags);

		if (!$log_exists) chmod($logname, 0777); //чтобы можно было читать
	}
}//filelog



//получить время
function getMicroTime(){
  list($usec, $sec) = explode(" ",microtime());
  return ((float)$usec + (float)$sec);
}

function showDeltaTime($time){
	echo "Время выполнения: ".(getMicroTime()-$time)." секунд";
}



/********************************************************************************
		Работа с GUID
*********************************************************************************/

//Возвращает сгенерированный случайным образом псевдо-GUID
function GUID(){
	//return md5(uniqid(rand()));

    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

        // 16 bits for "time_mid"
        mt_rand( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}//GUID


/********************************************************************************
		Работа с шаблонами
*********************************************************************************/
//Заменяет в шаблоене-строке $template ключи на значения в $data и возвращает преобразованную строку
//Примеры выражений: "{caption} ({code})", "{caption}-{articul}-{code}"
function template($template, $data){
//e("template($template)".strlen($template));

//e(__METHOD__);
//e($template);
//e($data);
	$res = is_string($template) ? $template : strval($template);

	if (is_array($data)){
		foreach($data as $key => $value){

			if (is_object($value)){
				$index = stripos($res,'{'.$key);

	            while ($index !== FALSE){
	            	//Нашли начало макроса
	            	//Теперь, это может быть параметр типа {contragent}, или {contragent.bankAccount.BIK}
	            	//Вобщем, вычленим макрос полностью
	            	$macro_start = $index + 1;

					//ищем закрывающую "}"
	            	$index = strpos($res, '}', $index + 1);

	            	if ($index !== FALSE){
	            		$macro = mb_substr($res, $macro_start,  $index - $macro_start); //"contragent" либо "contragent.bankAccount.BIK"

	            		//Проверим макрос, есть ли там "." ?
	            		$arr_path = explode('.', $macro);

	            		$replace_text = '';
	            		if (($c = count($arr_path)) > 1){
	            			//Да, это идет расшифровка полей объекта, типа "contragent.bankAccount.BIK"
	            			$f = $value; //текущий объект
	            			for($i = 1; $i<$c; $i++){//$v - это имя поля
	            				$vv = $arr_path[$i];

	            				//Пытаемся расшифровать поле
	            				if (is_object($f)) $f = $f->$vv; //переходим к очередному полю
	            				else {$f = NULL; break;}
	            			}

	            			$replace_text = ($f == NULL) ? '' : strval($f);
	            		}else{
	            			//Просто заменим выражение
	            			$replace_text = strval($value);
	            		}

						$res = substr_replace($res, $replace_text,
							$macro_start - 1, $index - $macro_start + 2);
	            	}

   					//Ищем очередное вхождение макроса
   					$index = stripos($res,'{'.$key, $index);

				}
            }else{
            	//Просто заменим макросы на значение
				$res = str_ireplace('{'.$key.'}', strval($value), $res);
            }
		}
	}
//e('результат: '.$res);
	return $res;
}//template


///


/********************************************************************************
		Работа со страницами
*********************************************************************************/




?>