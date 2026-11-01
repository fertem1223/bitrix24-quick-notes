<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Старый адрес оставлен для совместимости с закладками версии 1.0.
// Сам интерфейс теперь открывается виджетом, поэтому возвращаем пользователя на портал.
LocalRedirect('/');
