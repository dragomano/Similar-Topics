# Similar Topics
* **Author:** Bugo [dragomano.ru](https://dragomano.ru/mods/similar-topics)
* **License:** [BSD 3](https://github.com/dragomano/Similar-Topics/blob/master/LICENSE)
* **Compatible with:** SMF 2.0.x / PHP 5.6+
* **Tested on:** PHP 7.4.18 / MariaDB 10.5.6
* **Hooks only:** Yes
* **Languages:** English, French, Russian, Spanish, Turkish

## Description
This mod displays a list of similar topics at the bottom of current topic page and when creating new topic.

This mod needs MySQL 5.6 or greater. But MariaDB 10.3+ or PostgreSQL 9.6+ is better :)
To search quicker, the mod adds an index for _subject_ column in `{db_prefix}messages table`.

### Features:
* Number of displayed similar topics (optional).
* Possibility to search similar topics only within the current board (optional).
* Ignored boards (optional).
* Permissions.

## Описание
Вывод списка похожих тем внизу страницы и при создании новой темы.

Модификация требует MySQL 5.6 или выше. В идеале — MariaDB 10.3+ или PostgreSQL 9.6.
Для нормальной работы мод добавляет индекс для столбца _subject_ в таблице `{db_prefix}messages table`.

### Особенности:
* В настройках задается количество выводимых похожих тем, а также интервал обновления кеша.
* Ограничение поиска похожих тем текущим разделом (опционально).
* Указание игнорируемых разделов (в которых НЕ будет отображаться список).
* Установка прав доступа на просмотр списка похожих тем.
