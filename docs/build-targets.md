# Build Targets — lphenom/redis

Этот документ описывает **систему меток build-target** для LPhenom Builder.

Builder компилирует монолит в два режима:
- **`shared`** — PHP runtime (shared hosting, Apache/Nginx + PHP-FPM)
- **`kphp`** — KPHP binary (компилированный статический бинарник)

Каждый файл пакета помечен в `@lphenom-build` аннотации заголовочного DocBlock.
Builder читает эти метки и решает, включать файл или нет.

---

## Метки `@lphenom-build`

| Метка | shared | kphp | Описание |
|-------|:------:|:----:|---------|
| `@lphenom-build shared,kphp` | ✅ | ✅ | Включается в оба режима (default для всего кроме Cli/) |
| `@lphenom-build shared` | ✅ | ❌ | Только PHP runtime (ext-redis, PDO и т.п.) |
| `@lphenom-build kphp` | ❌ | ✅ | Только KPHP binary |
| `@lphenom-build none` | ❌ | ❌ | Никогда не включается (dev-инструменты, TUI) |

---

## Карта файлов lphenom/redis

### ✅ shared + kphp — `@lphenom-build shared,kphp`

Эти файлы включаются в **оба** режима. Они не зависят от ext-redis и совместимы с KPHP.

| Файл | Описание |
|------|----------|
| `src/Exception/RedisException.php` | Базовое исключение |
| `src/Exception/RedisConnectionException.php` | Ошибка подключения |
| `src/Exception/RedisCommandException.php` | Ошибка команды |
| `src/Client/RedisClientInterface.php` | Публичный API |
| `src/Pipeline/RedisPipelineDriverInterface.php` | Pipeline driver contract |
| `src/Pipeline/RedisPipeline.php` | Pipeline execution |
| `src/PubSub/MessageHandlerInterface.php` | PubSub handler contract |
| `src/PubSub/RedisPublisher.php` | Publisher через RespClient |
| `src/Connection/RedisConnectionConfig.php` | Config value object |
| `src/Resp/RespClient.php` | TCP RESP client (KPHP-compatible) |
| `src/Resp/RespPipelineDriver.php` | RESP pipeline driver |
| `src/Client/RespRedisClient.php` | RESP-based client (KPHP-compatible) |

### ⚠️ shared only — `@lphenom-build shared`

Эти файлы включаются **только в PHP runtime**. Зависят от `ext-redis`.
В KPHP binary их нет — используется `RespRedisClient` вместо `PhpRedisClient`.

| Файл | Причина исключения из KPHP |
|------|---------------------------|
| `src/Client/PhpRedisClient.php` | Зависит от `\Redis` (ext-redis) |
| `src/Pipeline/PhpRedisPipelineDriver.php` | Зависит от `\Redis` (ext-redis) |
| `src/Connection/RedisConnector.php` | Создаёт `PhpRedisClient` (ext-redis) + `RespRedisClient` — включить только shared; в KPHP монолите подключение создаётся через `RespRedisClient` напрямую |
| `src/PubSub/RedisSubscriber.php` | `\Redis::subscribe()` — ext-redis |

### ❌ dev / CLI tools — `@lphenom-build none`

Эти файлы **никогда** не включаются ни в shared, ни в kphp build.
Только для локального использования через `bin/redis-tui`.

| Файл | Причина |
|------|---------|
| `src/Cli/**` | `shell_exec()`, `system()`, `STDIN`, `\Redis::subscribe()` — всё KPHP и production несовместимо |

---

## Как Builder читает метки

Builder (пакет `lphenom/build`, будущий) сканирует PHP-файлы и извлекает `@lphenom-build` из DocBlock класса:

```php
// Пример чтения метки без Reflection:
// Builder читает файл как текст через file() и ищет @lphenom-build через strpos().
// Никакого Reflection — Builder сам KPHP-compatible.

$content = implode('', file($filePath));
$hasBuildTag = strpos($content, '@lphenom-build') !== false;
```

### Пример DocBlock с меткой

```php
<?php
declare(strict_types=1);

/**
 * @lphenom-build shared,kphp
 *
 * Redis client interface — KPHP-compatible.
 */
interface RedisClientInterface
{
    // ...
}
```

```php
<?php
declare(strict_types=1);

/**
 * @lphenom-build shared
 *
 * PHP-only Redis client using ext-redis.
 * NOT included in KPHP binary — use RespRedisClient instead.
 */
final class PhpRedisClient implements RedisClientInterface
{
    // ...
}
```

```php
<?php
declare(strict_types=1);

/**
 * @lphenom-build none
 *
 * TUI screen — dev tool only.
 * Never included in shared or kphp build.
 */
final class KeyListScreen implements ScreenInterface
{
    // ...
}
```

---

## Как Builder генерирует KPHP entrypoint

Builder обходит все файлы пакета, фильтрует по `@lphenom-build shared,kphp` или `@lphenom-build kphp`, и генерирует `require_once` список в порядке зависимостей:

```php
// Сгенерированный Builder-ом build/kphp-entrypoint.php для монолита

// lphenom/redis — только KPHP-совместимые файлы
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

// Файлы с @lphenom-build shared и @lphenom-build none — пропускаются
```

## Как Builder собирает shared PHAR

Для shared PHAR Builder исключает `@lphenom-build none` (dev-инструменты),
но включает `@lphenom-build shared` и `@lphenom-build shared,kphp`:

```
@lphenom-build shared,kphp  → включить в PHAR
@lphenom-build shared       → включить в PHAR
@lphenom-build kphp         → исключить из PHAR
@lphenom-build none         → исключить из PHAR
```

> `src/Cli/` уже исключён через `exclude-from-classmap` в `composer.json`.
> Builder дополнительно фильтрует через метки как второй уровень защиты.

---

## Связь с composer.json

Метки `@lphenom-build` работают **совместно** с `composer.json` autoload-изоляцией.
Это два независимых уровня защиты:

| Уровень | Механизм | Защищает от |
|---------|----------|-------------|
| 1 | `composer.json` `autoload` (точечные PSR-4) | `src/Cli/` не в Composer autoload |
| 2 | `composer.json` `exclude-from-classmap` | `src/Cli/` не в optimized classmap |
| 3 | `@lphenom-build none` в DocBlock | Builder явно пропускает файл |

Три уровня гарантируют, что KPHP-несовместимый код **никогда** не попадёт в binary build.

---

## Таблица быстрого референса

```
Файл                                    | shared | kphp
----------------------------------------|--------|------
src/Exception/*.php                     |   ✅   |  ✅
src/Client/RedisClientInterface.php     |   ✅   |  ✅
src/Client/RespRedisClient.php          |   ✅   |  ✅
src/Client/PhpRedisClient.php           |   ✅   |  ❌  (ext-redis)
src/Resp/RespClient.php                 |   ✅   |  ✅
src/Resp/RespPipelineDriver.php         |   ✅   |  ✅
src/Pipeline/RedisPipelineDriverInterface.php | ✅ | ✅
src/Pipeline/RedisPipeline.php          |   ✅   |  ✅
src/Pipeline/PhpRedisPipelineDriver.php |   ✅   |  ❌  (ext-redis)
src/PubSub/MessageHandlerInterface.php  |   ✅   |  ✅
src/PubSub/RedisPublisher.php           |   ✅   |  ✅
src/PubSub/RedisSubscriber.php          |   ✅   |  ❌  (ext-redis subscribe)
src/Connection/RedisConnectionConfig.php|   ✅   |  ✅
src/Connection/RedisConnector.php       |   ✅   |  ❌  (создаёт PhpRedisClient)
src/Cli/**                              |   ❌   |  ❌  (dev-only TUI)
```

