# redis-tui — Interactive Redis TUI

`redis-tui` — интерактивный табличный терминальный UI для работы с Redis, встроенный в пакет `lphenom/redis`.

Написан на чистом PHP с ANSI escape-кодами — без зависимостей от `ext-ncurses`.

---

## Запуск

```bash
# Через Composer bin
vendor/bin/redis-tui

# С параметрами подключения
vendor/bin/redis-tui --host=127.0.0.1 --port=6379 --password=secret --db=0

# Из конфиг-файла
vendor/bin/redis-tui --config=redis.config.php
```

### Через Docker Compose

```bash
# Запустить контейнер с Redis и PHP
make up

# Открыть TUI внутри контейнера
docker compose exec php vendor/bin/redis-tui
```

---

## Параметры подключения

| Аргумент | Переменная окружения | По умолчанию | Описание |
|----------|---------------------|--------------|----------|
| `--host=<h>` | `REDIS_HOST` | `127.0.0.1` | Redis hostname |
| `--port=<p>` | `REDIS_PORT` | `6379` | Redis port |
| `--password=<pw>` | `REDIS_PASSWORD` или `REDIS_AUTH` | _(нет)_ | Auth password |
| `--db=<n>` | `REDIS_DB` или `REDIS_DATABASE` | `0` | Database index |
| `--config=<file>` | — | — | PHP-файл с массивом конфига |

**Приоритет:** CLI аргументы > переменные окружения > конфиг-файл > дефолты.

### Формат конфиг-файла

```php
<?php
// redis.config.php
return [
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'password' => '',
    'database' => 0,
];
```

---

## Требования

- PHP 8.1+
- `ext-redis` (расширение PhpRedis)
- Терминал с поддержкой ANSI escape-кодов (iTerm2, xterm, gnome-terminal и т.д.)

---

## Экраны и навигация

### 🗝 Key List Screen (главный экран)

Отображает все ключи Redis в табличном виде.

| Колонки | Описание |
|---------|----------|
| Key | Имя ключа |
| Type | Тип (string / list / hash / set / zset) |
| TTL | Время жизни (∞ = без ограничений) |
| Encoding | Внутренняя кодировка |

**Горячие клавиши:**

| Клавиша | Действие |
|---------|----------|
| `↑` / `↓` | Навигация по списку |
| `PgUp` / `PgDn` | Перелистывание страниц |
| `Enter` | Открыть значение ключа |
| `/` | Активировать фильтр (поддержка паттернов SCAN MATCH) |
| `d` | Удалить выбранный ключ (с подтверждением) |
| `r` | Обновить список ключей |
| `p` | Перейти в Pipeline Builder |
| `s` | Перейти в PubSub Monitor |
| `q` / `Esc` | Выйти |
| `Ctrl+C` | Принудительный выход |

**Фильтрация ключей:**

Нажмите `/`, введите паттерн (например `user:*`, `session:*`, `cache:*`) и нажмите `Enter`. Паттерн передаётся в `SCAN MATCH`.

---

### 📋 Value View Screen

Просмотр и редактирование значений ключа. Интерфейс адаптируется под тип данных.

#### STRING

```
Key: mykey  [STRING]
TTL: no expiry

Value:
┌────────────────────────────┐
│ hello world                │
└────────────────────────────┘
```

| Клавиша | Действие |
|---------|----------|
| `e` | Редактировать значение |
| `d` | Удалить ключ |
| `t` | Установить TTL |
| `Esc` | Вернуться к списку ключей |

#### LIST

Отображает элементы через `LRANGE 0 199`.

| Клавиша | Действие |
|---------|----------|
| `a` | LPUSH — добавить в начало |
| `d` | LREM — удалить выбранный элемент |

#### HASH

Отображает все поля через `HGETALL`.

| Клавиша | Действие |
|---------|----------|
| `e` | HSET — редактировать значение поля |
| `a` | HSET — добавить новое поле |
| `d` | HDEL — удалить поле |

#### SET

Отображает члены через `SMEMBERS`.

| Клавиша | Действие |
|---------|----------|
| `a` | SADD — добавить член |
| `d` | SREM — удалить выбранный член |

#### ZSET (Sorted Set)

Отображает member + score через `ZRANGE WITHSCORES`.

| Клавиша | Действие |
|---------|----------|
| `e` | ZADD — обновить score выбранного члена |
| `a` | ZADD — добавить member:score |
| `d` | ZREM — удалить выбранный член |

---

### ⚡ Pipeline Builder Screen

Визуальный конструктор пакетных команд Redis.

```
redis-tui  ◆  Pipeline Builder
Queued commands: 3

# │ Command │ Key                │ Value     │ TTL
──┼─────────┼────────────────────┼───────────┼─────
1 │ SET     │ cache:user:42      │ {"id":42} │ 300
2 │ INCR    │ visits:today       │           │ 0
3 │ EXPIRE  │ session:abc        │ 3600      │ 0
```

**Горячие клавиши:**

| Клавиша | Действие |
|---------|----------|
| `a` | Добавить команду (открывает визард выбора команды) |
| `d` | Удалить выбранную команду |
| `x` | Выполнить все команды пайплайна |
| `c` | Очистить пайплайн (с подтверждением) |
| `↑` / `↓` | Навигация по командам |
| `Esc` | Вернуться к списку ключей |

**Поддерживаемые команды в пайплайне:**

| Команда | Аргументы | Описание |
|---------|-----------|----------|
| `SET` | key, value, TTL | Установить значение |
| `DEL` | key | Удалить ключ |
| `INCR` | key | Инкремент счётчика |
| `LPUSH` | key, value | Добавить в начало списка |
| `EXPIRE` | key, seconds | Установить TTL |

---

### 📡 PubSub Monitor Screen

Реалтайм мониторинг сообщений Redis Pub/Sub.

```
redis-tui  ◆  PubSub Monitor
Subscribed: events.*, notifications

[14:32:01] <events.user> {"event":"login","userId":42}
[14:32:05] <notifications> System maintenance in 5 minutes
[14:32:10] >>> Published to [alerts]: test message
```

**Горячие клавиши:**

| Клавиша | Действие |
|---------|----------|
| `s` | Подписаться на канал или паттерн |
| `u` | Отписаться от всех каналов |
| `p` | Опубликовать сообщение |
| `c` | Очистить лог сообщений |
| `↑` / `↓` | Прокрутка лога |
| `Home` / `End` | В начало / в конец лога |
| `PgUp` / `PgDn` | Перелистывание страниц лога |
| `Esc` | Вернуться к списку ключей |

**Паттерны подписки:**

Если паттерн содержит `*` или `?`, используется `PSUBSCRIBE`, иначе `SUBSCRIBE`.

```
events.*       → PSUBSCRIBE events.*
notifications  → SUBSCRIBE notifications
user:*:events  → PSUBSCRIBE user:*:events
```

**Лог** хранит последние 500 сообщений (кольцевой буфер) и автоматически прокручивается к последнему сообщению.

---

## Архитектура

```
bin/redis-tui               ← точка входа, CLI bootstrap
src/Cli/
  Config/
    CliConfigLoader         ← загрузка параметров подключения
  Terminal/
    Terminal                ← raw mode, размеры терминала
    InputReader             ← неблокирующее чтение STDIN
    KeyPress                ← событие нажатия клавиши
    Renderer                ← буферизованный ANSI вывод
  Widget/
    TableWidget             ← таблица с навигацией
    InputWidget             ← строка ввода с курсором
    StatusBar               ← нижняя строка состояния
    ModalDialog             ← модальные диалоги
  Screen/
    ScreenInterface         ← контракт экрана
    ScreenRouter            ← event loop + роутинг
    KeyListScreen           ← список ключей
    ValueViewScreen         ← просмотр/редактирование значений
    PipelineBuilderScreen   ← визуальный пайплайн
    PubSubScreen            ← мониторинг pub/sub
  Adapter/
    RawRedisAdapter         ← обёртка для расширенных команд Redis
```

---

## Цветовая схема

| Элемент | Цвет |
|---------|------|
| Заголовок (Key List) | Синий фон |
| Заголовок (Pipeline) | Фиолетовый фон |
| Заголовок (PubSub) | Зелёный фон |
| Выбранная строка | Голубой фон, чёрный текст |
| Режим (Status bar) | Голубой фон |
| Ошибки | Красный фон |
| Успех | Зелёный фон |
| Подсказки | Тёмно-серый текст |
| Границы | Ярко-синий |
| Значения | Ярко-белый |

---

## Требования к терминалу

- Unicode Box Drawing Characters (U+2500–U+257F)
- ANSI 256-color или 16-color
- Минимальный размер: 80×24

Проверено в: iTerm2, GNOME Terminal, xterm, Alacritty, tmux.

