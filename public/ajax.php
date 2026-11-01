<?php

// Облегчённая загрузка ядра: статистика и агенты для короткого AJAX-запроса не нужны.
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('DisableEventsCheck', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

global $USER;
header('Content-Type: application/json; charset=UTF-8');

/** Завершает запрос единообразным JSON-ответом. */
function quickNotesResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Никогда не принимаем userId от браузера: владелец определяется текущей сессией.
if (!$USER->IsAuthorized()) {
    quickNotesResponse(['success' => false, 'error' => 'Требуется авторизация'], 401);
}

// sessid защищает добавление и удаление от межсайтовой подделки запроса (CSRF).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    quickNotesResponse(['success' => false, 'error' => 'Сессия истекла. Обновите страницу.'], 403);
}

$userId = (int)$USER->GetID();
// CUserOptions хранит значение отдельно для каждого пользователя в базе Битрикс.
$notes = CUserOptions::GetOption('local.quicknotes', 'notes', [], $userId);
if (!is_array($notes)) {
    $notes = [];
}

// Однократный перенос из версии 1.0, где у пользователя было одно текстовое поле.
if ($notes === []) {
    $legacyNote = trim((string)CUserOptions::GetOption('local.quicknotes', 'personal_notes', '', $userId));
    if ($legacyNote !== '') {
        $notes[] = [
            'id' => bin2hex(random_bytes(8)),
            'text' => $legacyNote,
            'createdAt' => date('d.m.Y H:i'),
        ];
        CUserOptions::SetOption('local.quicknotes', 'notes', $notes, false, $userId);
        CUserOptions::DeleteOption('local.quicknotes', 'personal_notes', false, $userId);
    }
}

$action = (string)($_POST['action'] ?? 'list');
if ($action === 'add') {
    // Серверная проверка обязательна, maxlength в браузере можно обойти.
    $text = trim((string)($_POST['text'] ?? ''));
    if ($text === '' || mb_strlen($text) > 2000) {
        quickNotesResponse(['success' => false, 'error' => 'Введите текст длиной до 2 000 символов.'], 422);
    }
    // Новая запись размещается первой; случайный id нужен для точного удаления.
    array_unshift($notes, [
        'id' => bin2hex(random_bytes(8)),
        'text' => $text,
        'createdAt' => date('d.m.Y H:i'),
    ]);
    // Ограничиваем объём CUserOptions: виджет хранит не более 100 последних записей.
    $notes = array_slice($notes, 0, 100);
    CUserOptions::SetOption('local.quicknotes', 'notes', $notes, false, $userId);
} elseif ($action === 'delete') {
    // array_values восстанавливает последовательные индексы перед сериализацией.
    $id = (string)($_POST['id'] ?? '');
    $notes = array_values(array_filter($notes, static fn(array $note): bool => ($note['id'] ?? '') !== $id));
    CUserOptions::SetOption('local.quicknotes', 'notes', $notes, false, $userId);
} elseif ($action !== 'list') {
    quickNotesResponse(['success' => false, 'error' => 'Неизвестное действие.'], 400);
}

quickNotesResponse(['success' => true, 'notes' => $notes]);
