(function () {
    'use strict';

    var endpoint = '/local/modules/local.quicknotes/public/ajax.php';

    // Превращаем пользовательский текст в безопасный HTML, предотвращая XSS.
    function escapeText(value) {
        var node = document.createElement('span');
        node.textContent = value;
        return node.innerHTML;
    }

    // Общая обёртка для запросов: добавляет Bitrix sessid и разбирает JSON.
    function request(action, data) {
        var body = new FormData();
        body.append('action', action);
        body.append('sessid', typeof BX !== 'undefined' ? BX.bitrix_sessid() : '');
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });

        return fetch(endpoint, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (!result.success) {
                throw new Error(result.error || 'Не удалось выполнить запрос');
            }
            return result;
        });
    }

    // Полностью перестраиваем небольшой список после каждой операции.
    function renderNotes(container, notes) {
        if (!notes.length) {
            container.innerHTML = '<div class="local-quicknotes-empty">Заметок пока нет</div>';
            return;
        }

        container.innerHTML = notes.map(function (note) {
            return '<article class="local-quicknotes-item" data-id="' + escapeText(note.id) + '">'
                + '<div class="local-quicknotes-item__text">' + escapeText(note.text).replace(/\n/g, '<br>') + '</div>'
                + '<div class="local-quicknotes-item__footer">'
                + '<time>' + escapeText(note.createdAt) + '</time>'
                + '<button type="button" class="local-quicknotes-delete" title="Удалить заметку">Удалить</button>'
                + '</div></article>';
        }).join('');
    }

    // Создаём боковую панель один раз; наружу возвращаем только метод открытия.
    function createWidget() {
        var overlay = document.createElement('div');
        overlay.className = 'local-quicknotes-overlay';
        overlay.innerHTML = '<section class="local-quicknotes-panel" role="dialog" aria-modal="true" aria-label="Мои заметки">'
            + '<header class="local-quicknotes-panel__header"><strong>Мои заметки</strong>'
            + '<button type="button" class="local-quicknotes-close" aria-label="Закрыть">×</button></header>'
            + '<form class="local-quicknotes-form">'
            + '<textarea maxlength="2000" required placeholder="Напишите новую заметку…"></textarea>'
            + '<div class="local-quicknotes-form__footer"><span class="local-quicknotes-error"></span>'
            + '<button type="submit" class="local-quicknotes-add">Добавить</button></div></form>'
            + '<div class="local-quicknotes-list"><div class="local-quicknotes-loading">Загрузка…</div></div>'
            + '</section>';
        document.body.appendChild(overlay);

        var panel = overlay.querySelector('.local-quicknotes-panel');
        var form = overlay.querySelector('.local-quicknotes-form');
        var textarea = form.querySelector('textarea');
        var list = overlay.querySelector('.local-quicknotes-list');
        var error = overlay.querySelector('.local-quicknotes-error');

        // Блокировка body не позволяет прокручивать страницу под открытым виджетом.
        function close() {
            overlay.classList.remove('local-quicknotes-overlay--open');
            document.body.classList.remove('local-quicknotes-body-locked');
        }

        // Список загружается при каждом открытии, поэтому данные всегда свежие.
        function load() {
            error.textContent = '';
            request('list').then(function (result) {
                renderNotes(list, result.notes);
            }).catch(function (exception) {
                error.textContent = exception.message;
            });
        }

        // Закрытие работает по крестику и по клику на затемнённую область.
        overlay.querySelector('.local-quicknotes-close').addEventListener('click', close);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                close();
            }
        });
        // Делегирование позволяет одной подпиской обслуживать все кнопки удаления.
        panel.addEventListener('click', function (event) {
            var button = event.target.closest('.local-quicknotes-delete');
            if (!button) {
                return;
            }
            var item = button.closest('.local-quicknotes-item');
            button.disabled = true;
            request('delete', {id: item.dataset.id}).then(function (result) {
                renderNotes(list, result.notes);
            }).catch(function (exception) {
                button.disabled = false;
                error.textContent = exception.message;
            });
        });
        // После добавления сервер возвращает готовый актуальный список.
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var text = textarea.value.trim();
            if (!text) {
                return;
            }
            form.querySelector('.local-quicknotes-add').disabled = true;
            request('add', {text: text}).then(function (result) {
                textarea.value = '';
                renderNotes(list, result.notes);
            }).catch(function (exception) {
                error.textContent = exception.message;
            }).finally(function () {
                form.querySelector('.local-quicknotes-add').disabled = false;
            });
        });

        return {
            open: function () {
                overlay.classList.add('local-quicknotes-overlay--open');
                document.body.classList.add('local-quicknotes-body-locked');
                textarea.focus();
                load();
            }
        };
    }

    function addLauncher() {
        // Защита от повторной вставки при динамической перезагрузке интерфейса Битрикс.
        if (document.querySelector('.local-quicknotes-launcher')) {
            return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'local-quicknotes-launcher';
        button.title = 'Открыть личные заметки';
        button.innerHTML = '<span class="local-quicknotes-launcher__icon">✎</span>'
            + '<span class="local-quicknotes-launcher__text">Мои заметки</span>';
        var widget = createWidget();
        button.addEventListener('click', widget.open);
        document.body.appendChild(button);
    }

    // Код может загрузиться как до, так и после события DOMContentLoaded.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addLauncher);
    } else {
        addLauncher();
    }
}());
