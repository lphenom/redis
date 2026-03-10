# lphenom/redis

[![CI](https://github.com/lphenom/redis/actions/workflows/ci.yml/badge.svg)](https://github.com/lphenom/redis/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net/)
[![KPHP Compatible](https://img.shields.io/badge/KPHP-compatible-green)](https://vkcom.github.io/kphp/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**lphenom/redis** — лёгкий Redis-клиент для LPhenom framework.

Совместим с PHP 8.1+ и KPHP (компиляция в статический бинарник).

## Возможности

- 🔌 **PhpRedisClient** — работа через `ext-redis`
- 🧪 **FfiRedisClient** — заглушка для KPHP FFI (stub)
- 🔗 **RedisConnector** — фабрика для создания клиентов
- 📦 **RedisPipeline** — batch-команды
- 📡 **PubSub** — публикация и подписка
- ⚡ **KPHP-совместимость** — без reflection, eval, динамики

## Установка

```bash
composer require lphenom/redis
```

Для работы через `ext-redis` установите расширение:

```bash
# Ubuntu/Debian
sudo apt-get install php-redis

# Alpine (Docker)
apk add php81-redis
```

## Быстрый старт

```php
use LPhenom\Redis\Connection\RedisConnectionConfig;
use LPhenom\Redis\Connection\RedisConnector;

$config = new RedisConnectionConfig(
    host: '127.0.0.1',
    port: 6379,
    password: '',
    database: 0,
    timeout: 2.0,
    persistent: false
);

$redis = RedisConnector::connect($config);

// Базовые операции
$redis->set('key', 'value', 60);
$val = $redis->get('key');         // 'value'
$redis->del('key');

// Очереди
$redis->lpush('queue', 'job1');
$job = $redis->rpop('queue');      // 'job1'

// Pub/Sub
$publisher = new \LPhenom\Redis\PubSub\RedisPublisher($redis);
$publisher->publish('channel', 'message');
```

## Pipeline

```php
$pipeline = $redis->pipeline();

$pipeline->set('a', '1');
$pipeline->set('b', '2');
$pipeline->incr('counter');

$pipeline->execute();
```

## Документация

- [Подключение и конфигурация](docs/redis.md)
- [Pipeline](docs/pipelines.md)
- [Pub/Sub](docs/pubsub.md)

## Разработка

```bash
# Запуск окружения
make up

# Тесты
make test

# Линтинг
make lint

# Остановка
make down
```

## KPHP-совместимость

Пакет не использует:
- `Reflection` API
- `eval()`
- `new $className()`
- `variable variables`
- `callable` в типизированных массивах

Проверка совместимости:

```bash
make kphp-check
```

## Лицензия

MIT — см. [LICENSE](LICENSE).
