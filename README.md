# Similar Topics
[![SMF 2.1](https://img.shields.io/badge/SMF-2.1-ed6033.svg?style=flat)](https://github.com/SimpleMachines/SMF2.1)
![License](https://img.shields.io/github/license/dragomano/similar-topics)
![PHP](https://img.shields.io/badge/PHP-^7.2-blue.svg?style=flat)
![Hooks only: Yes](https://img.shields.io/badge/Hooks%20only-YES-blue)

* **Tested on:** PHP 7.4.28 / MariaDB 10.6.5
* **Languages:** English, French, Russian, Spanish, Turkish, Italian

## Description
This mod displays a list of similar topics at the bottom of current topic page and when creating new topic.

This mod needs MySQL 5.6+, MariaDB 10.3+ or PostgreSQL 9.6+.
To search quicker, the mod adds an index for _subject_ column in `{db_prefix}messages` table.

### Features:
* Number of displayed similar topics (optional).
* Possibility to search similar topics only within the current board (optional).
* Ignored boards (optional).
* Permissions.

## Описание
Вывод списка похожих тем внизу страницы и при создании новой темы.

Модификация требует MySQL 5.6 или выше. В идеале — MariaDB 10.3+ или PostgreSQL 9.6.
Для нормальной работы мод добавляет индекс для столбца _subject_ в таблице `{db_prefix}messages`.

### Особенности:
* В настройках задается количество выводимых похожих тем, а также интервал обновления кеша.
* Ограничение поиска похожих тем текущим разделом (опционально).
* Указание игнорируемых разделов (в которых НЕ будет отображаться список).
* Установка прав доступа на просмотр списка похожих тем.
