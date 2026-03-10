# KPHP Compatibility

Пакет `lphenom/redis` полностью совместим с KPHP компиляцией.

## RespRedisClient — полноценный клиент для KPHP

В отличие от `lphenom/db` (где PDO отсутствует в KPHP и пришлось делать FFI через `libmysqlclient`),
для Redis решение элегантнее — **чистый TCP + RESP протокол**.

### Почему не FFI для Redis?

| Подход | Требует | KPHP? |
|--------|---------|-------|
| `ext-redis` (PhpRedisClient) | PHP расширение | ❌ нет в бинарнике |
| FFI + libhiredis | `libhiredis.so` на сервере | ✅ но сложно |
| TCP + RESP (RespRedisClient) | ничего, только сеть | ✅ **лучший вариант** |

### Как работает RespRedisClient

```
RespRedisClient
    └── RespClient (TCP socket)
            ├── stream_socket_client()   ← KPHP-поддерживается
            ├── fwrite()                 ← KPHP-поддерживается
            ├── fgets()                  ← KPHP-поддерживается
            ├── fread()                  ← KPHP-поддерживается
            └── fclose()                 ← KPHP-поддерживается
```

KPHP поддерживает `stream_socket_client()` (но **не** `fsockopen()`).
Все остальные stream-функции тоже доступны.

### Что НЕ поддерживается в KPHP (обход через RESP)

| Недоступно в KPHP | Что используем вместо |
|-------------------|-----------------------|
| `ext-redis` / `\Redis` класс | `RespClient` (TCP) |
| `fsockopen()` | `stream_socket_client()` |
| `stream_set_timeout()` | timeout передаётся в `stream_socket_client()` |
| `@var resource` аннотация | `@var mixed` (KPHP не знает тип resource) |

### Выбор драйвера

```php
// PHP runtime (shared hosting) — автоматически ext-redis
// KPHP binary — автоматически RESP TCP
$redis = RedisConnector::connect($config);

// Явно выбрать RESP (для KPHP entrypoint):
$redis = RedisConnector::connectResp($config);
```

## Проверка совместимости

```bash
# Собрать KPHP binary + PHAR
make kphp-check
# или
docker build -f Dockerfile.check -t lphenom-redis-check .
```

## Что соблюдается

### ✅ Структура кода

- `declare(strict_types=1)` во всех файлах
- Явное объявление свойств (нет constructor property promotion)
- Нет `readonly` свойств
- Нет `Reflection` API
- Нет `eval()`
- Нет `new $className()` (динамическая загрузка)
- Нет `$$varName` (variable variables)

### ✅ Типизация

- Нет `callable` в типизированных массивах
- Все массивы аннотированы: `@var array<int, array<int, string>>`
- Явные `null`-проверки вместо `!isset() + throw`

### ✅ Обработка ошибок

- `try/catch` с явными catch-блоками (нет `try/finally` без `catch`)
- Паттерн сохранения исключения в переменную:

```php
$exception = null;
$result    = null;
try {
    $result = $this->redis->get($key);
} catch (\RedisException $e) {
    $exception = new RedisCommandException(...);
}
if ($exception !== null) {
    throw $exception;
}
```

### ✅ Альтернативы запрещённым конструкциям

| Запрещено в KPHP | Что используется |
|-----------------|-----------------|
| `callable` в массивах | `MessageHandlerInterface` |
| `str_starts_with()` | `substr()` / `strpos()` |
| Constructor property promotion | Явные `private string $prop;` |
| `readonly` | Обычные `private` свойства |

## KPHP entrypoint

KPHP не поддерживает Composer autoloading. Все файлы загружаются явно:

```php
// build/kphp-entrypoint.php
require_once __DIR__ . '/../src/Exception/RedisException.php';
require_once __DIR__ . '/../src/Exception/RedisConnectionException.php';
// ...
```

Порядок: исключения → интерфейсы → реализации.

## RespRedisClient — полноценный клиент для KPHP

- [KPHP Documentation](https://vkcom.github.io/kphp/)
- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)

