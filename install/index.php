<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/** Описание модуля для стандартного установщика Битрикс. */
class local_quicknotes extends CModule
{
    public $MODULE_ID = 'local.quicknotes';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME = 'Local Development';
    public $PARTNER_URI = '';

    public function __construct()
    {
        // Версия вынесена в стандартный для модулей Битрикс файл.
        $version = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $version['VERSION'];
        $this->MODULE_VERSION_DATE = $version['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('LOCAL_QUICKNOTES_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('LOCAL_QUICKNOTES_MODULE_DESCRIPTION');
    }

    public function DoInstall(): void
    {
        // Регистрируем модуль и подключаем ассеты перед построением публичной страницы.
        RegisterModule($this->MODULE_ID);
        EventManager::getInstance()->registerEventHandler(
            'main',
            'OnProlog',
            $this->MODULE_ID,
            'LocalQuickNotes',
            'addAssets'
        );
    }

    public function DoUninstall(): void
    {
        // Сначала снимаем обработчик, затем удаляем запись о модуле.
        // Пользовательские заметки намеренно не удаляются, чтобы их можно было вернуть.
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnProlog',
            $this->MODULE_ID,
            'LocalQuickNotes',
            'addAssets'
        );
        UnRegisterModule($this->MODULE_ID);
    }
}
