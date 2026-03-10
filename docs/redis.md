# Redis Client

`LPhenom\Redis` — лёгкий Redis-клиент для LPhenom framework с поддержкой KPHP.

## Архитектура драйверов

```
RedisClientInterface
    ├── PhpRedisClient      — PHP runtime (ext-redis)      — shared hosting
    ├── RespRedisClient     — PHP + KPHP (TCP + RESP)      — compiled binary
    └── FfiRedisClient      — KPHP stub (future FFI)       — placeholder
```

`RedisConnector::connect()` автоматически выбирает драйвер:
- Если `ext-redis` загружен → `PhpRedisClient`
- Иначе → `RespRedisClient` (работает в KPHP-бинарнике)

## Подключение

### Автовыбор драйвера (рекомендуется)

```php
use LPhenom\Redis\Connection\RedisConnectionConfig;
use LPhenom\Redis\Connection\RedisConnector;

$config = new RedisConnectionConfig(
    '127.0.0.1', // host
    6379,         // port
    '',           // password
    0,            // database
    2.0,          // timeout
    false         // persistent
);

// Автовыбор: ext-redis → PhpRedisClient, иначе → RespRedisClient
$redis = RedisConnector::connect($config);
```

### Явный выбор драйвера

```php
// Только ext-redis (PHP runtime / shared hosting)
$redis = RedisConnector::connectPhpRedis($config);

// Только RESP TCP (KPHP binary / без ext-redis)
$redis = RedisConnector::connectResp($config);
```

### RedisConnectionConfig параметры

| Параметр     | Тип    | По умолчанию | Описание                            |
|--------------|--------|--------------|-------------------------------------|
| `host`       | string | `127.0.0.1`  | Хост Redis-сервера                  |
| `port`       | int    | `6379`       | Порт Redis-сервера                  |
| `password`   | string | `''`         | AUTH-пароль (пустая строка = нет)   |
| `database`   | int    | `0`          | Индекс базы данных                  |
| `timeout`    | float  | `2.0`        | Таймаут подключения в секундах      |
| `persistent` | bool   | `false`      | Persistent connections (ext-redis)  |

## Базовые операции

```php
// Получение значения
$value = $redis->get('key');         // string|null

// Запись с TTL (в секундах)
$redis->set('key', 'value', 3600);  // expires in 1 hour

// Запись без TTL
$redis->set('key', 'value');

// Удаление
$redis->del('key');

// Проверка существования
if ($redis->exists('key')) {
    // ...
}

// Инкремент
$count = $redis->incr('counter');   // int

// Установка времени жизни
$redis->expire('key', 300);         // 5 минут
```

## Очереди (List-based)

```php
// Добавить элемент в начало списка
$redis->lpush('queue', 'job1');

// Извлечь элемент с конца (FIFO-очередь)
$job = $redis->rpop('queue');       // string|null

// Блокирующее чтение (ждать до $timeout секунд)
$job = $redis->blpop('queue', 5);   // string|null, null = timeout
```

## Исключения

| Класс                       | Когда бросается                          |
|-----------------------------|------------------------------------------|
| `RedisConnectionException`  | Не удалось подключиться или авторизоваться |
| `RedisCommandException`     | Команда Redis вернула ошибку             |
| `RedisException`            | Базовый класс                            |

```php
use LPhenom\Redis\Exception\RedisConnectionException;
use LPhenom\Redis\Exception\RedisCommandException;

try {
    $redis = RedisConnector::connect($config);
    $value = $redis->get('key');
} catch (RedisConnectionException $e) {
    // Не удалось подключиться
    echo 'Connection failed: ' . $e->getMessage();
} catch (RedisCommandException $e) {
    // Команда не выполнилась
    echo 'Command failed: ' . $e->getMessage();
}
```

## KPHP-совместимость

| Компонент             | Статус              |
|-----------------------|---------------------|
| `PhpRedisClient`      | ✅ KPHP-совместим   |
| `FfiRedisClient`      | ✅ Stub (заглушка)  |
| `RedisConnector`      | ✅ KPHP-совместим   |
| `RedisConnectionConfig` | ✅ KPHP-совместим |

> Подробнее о правилах KPHP-совместимости — в [kphp-compatibility.md](./kphp-compatibility.md).

