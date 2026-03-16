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

---

## src/Cli/ — намеренно исключён из production autoload

`src/Cli/` содержит интерактивный TUI-инструмент (`redis-tui`).
Этот код **несовместим с KPHP** и **несовместим с продакшн-окружением**:

| Файл | Причина несовместимости |
|------|------------------------|
| `Terminal/Terminal.php` | `shell_exec()`, `system()` — недоступны в KPHP |
| `Terminal/InputReader.php` | `STDIN`, `stream_select()` с файловым дескриптором — CLI-only |
| `Adapter/RawRedisAdapter.php` | `\Redis` класс — ext-redis, нет в KPHP binary |
| `Screen/PipelineBuilderScreen.php` | `\Redis::multi()`, `\Redis::PIPELINE` |
| `Screen/PubSubScreen.php` | `\Redis::subscribe()`, `\Redis::psubscribe()` |

### Как это решено

В `composer.json` `src/Cli/` **полностью исключён** из production autoload:

```json
{
  "autoload": {
    "psr-4": {
      "LPhenom\\Redis\\Client\\":     "src/Client/",
      "LPhenom\\Redis\\Connection\\": "src/Connection/",
      "LPhenom\\Redis\\Exception\\":  "src/Exception/",
      "LPhenom\\Redis\\Pipeline\\":   "src/Pipeline/",
      "LPhenom\\Redis\\PubSub\\":     "src/PubSub/",
      "LPhenom\\Redis\\Resp\\":       "src/Resp/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "LPhenom\\Redis\\Cli\\": "src/Cli/"
    }
  }
}
```

`src/Cli/` зарегистрирован только в `autoload-dev` — он **не попадает** в
`vendor/composer/autoload_psr4.php` и `autoload_classmap.php` когда пакет
устанавливается как зависимость (`composer install --no-dev`).

### Что это означает для монолита на KPHP

Когда вы добавляете `lphenom/redis` в свой проект:

```bash
composer require lphenom/redis:^0.1
```

В `vendor/composer/autoload_psr4.php` появятся только:

```
LPhenom\Redis\Client\     → vendor/lphenom/redis/src/Client/
LPhenom\Redis\Connection\ → vendor/lphenom/redis/src/Connection/
LPhenom\Redis\Exception\  → vendor/lphenom/redis/src/Exception/
LPhenom\Redis\Pipeline\   → vendor/lphenom/redis/src/Pipeline/
LPhenom\Redis\PubSub\     → vendor/lphenom/redis/src/PubSub/
LPhenom\Redis\Resp\       → vendor/lphenom/redis/src/Resp/
```

`LPhenom\Redis\Cli\` — **не появится**. KPHP никогда не увидит TUI-файлы.

### bin/redis-tui загружает Cli явно

`bin/redis-tui` обходит этот запрет через явные `require_once`:

```php
// bin/redis-tui
if (!class_exists(\LPhenom\Redis\Cli\Terminal\Terminal::class, false)) {
    require_once $cliBase . '/Terminal/KeyPress.php';
    require_once $cliBase . '/Terminal/Terminal.php';
    // ... все Cli файлы явно
}
```

Это работает в обоих случаях:
- Локальная разработка (`composer install` с dev): классы уже загружены autoload-dev
- Установлен в монолит (`--no-dev`): `require_once` загружает файлы напрямую из `vendor/lphenom/redis/src/Cli/`

### KPHP entrypoint монолита — пример

Ваш `build/kphp-entrypoint.php` в монолите должен включать только KPHP-совместимые файлы пакета:

```php
// ✅ KPHP-совместимые файлы lphenom/redis
require_once __DIR__ . '/../vendor/lphenom/redis/src/Exception/RedisException.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Exception/RedisConnectionException.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Exception/RedisCommandException.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Pipeline/RedisPipelineDriverInterface.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Pipeline/RedisPipeline.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Client/RedisClientInterface.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Resp/RespClient.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Resp/RespPipelineDriver.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Client/RespRedisClient.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Connection/RedisConnectionConfig.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/PubSub/MessageHandlerInterface.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/PubSub/RedisPublisher.php';

// ❌ НЕ ВКЛЮЧАТЬ — несовместимо с KPHP:
// require_once __DIR__ . '/../vendor/lphenom/redis/src/Cli/...';
```

---

## RespRedisClient — полноценный клиент для KPHP

- [KPHP Documentation](https://vkcom.github.io/kphp/)
- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)

