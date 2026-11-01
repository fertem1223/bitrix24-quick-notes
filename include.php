<?php

use Bitrix\Main\Page\Asset;

/**
 * Подключает клиентскую часть виджета к публичным страницам портала.
 * Класс загружается автоматически при подключении модуля local.quicknotes.
 */
final class LocalQuickNotes
{
    /** Обработчик события main:OnProlog. */
    public static function addAssets(): void
    {
        global $USER, $APPLICATION;

        // Гостям виджет не нужен: AJAX-обработчик всё равно доступен только после входа.
        if (!is_object($USER) || !$USER->IsAuthorized() || !is_object($APPLICATION)) {
            return;
        }

        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        // Не вмешиваемся в CLI, административную часть, AJAX и собственные endpoints.
        if (
            PHP_SAPI === 'cli'
            || defined('ADMIN_SECTION')
            || defined('PUBLIC_AJAX_MODE')
            || str_starts_with($requestUri, '/bitrix/')
            || str_starts_with($requestUri, '/local/modules/local.quicknotes/public/')
        ) {
            return;
        }

        // Asset помещает ссылки в <head> с учётом объединения и кеширования Битрикс.
        $asset = Asset::getInstance();
        $asset->addCss('/local/modules/local.quicknotes/assets/quicknotes.css');
        $asset->addJs('/local/modules/local.quicknotes/assets/quicknotes.js');
    }
}
