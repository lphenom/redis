# Pub/Sub

Redis Pub/Sub для realtime-событий, очередей уведомлений и межсервисного взаимодействия.

## Публикация сообщений

`RedisPublisher` — простая обёртка для отправки сообщений в канал.

```php
use LPhenom\Redis\PubSub\RedisPublisher;

$publisher = new RedisPublisher($redis);

// Отправить сообщение
$publisher->publish('events', 'user.created');

// Отправить JSON-сообщение
$payload = json_encode(['type' => 'user.created', 'id' => 42]);
if ($payload !== false) {
    $publisher->publish('events', $payload);
}
```

## Подписка на канал

`RedisSubscriber` требует **отдельного Redis-соединения**. После вызова `subscribe()` соединение переходит в режим pub/sub и не может использоваться для других команд.

### MessageHandlerInterface

KPHP-совместимый способ обработки сообщений — через интерфейс вместо callable:

```php
use LPhenom\Redis\PubSub\MessageHandlerInterface;

class UserEventHandler implements MessageHandlerInterface
{
    public function handle(string $channel, string $message): void
    {
        $data = json_decode($message, true);
        if ($data === null) {
            return;
        }
        // Обработка события
        echo 'Received on ' . $channel . ': ' . $message . PHP_EOL;
    }
}
```

### Подписка

```php
use LPhenom\Redis\PubSub\RedisSubscriber;

// ВАЖНО: отдельное соединение для подписки
$subscriberRedis = new \Redis();
$subscriberRedis->connect('127.0.0.1', 6379);

$subscriber = new RedisSubscriber($subscriberRedis);

// Блокирует выполнение — вызывает handle() для каждого сообщения
$subscriber->subscribe('events', new UserEventHandler());
```

### Подписка по паттерну

```php
// Подписаться на все каналы, начинающиеся с "user."
$subscriber->psubscribe('user.*', new UserEventHandler());
```

## KPHP-совместимость

| Компонент             | Статус              |
|-----------------------|---------------------|
| `RedisPublisher`      | ✅ KPHP-совместим   |
| `MessageHandlerInterface` | ✅ KPHP-совместим |
| `RedisSubscriber`     | ⚠️ Требует ext-redis (не stub) |

`RedisSubscriber` использует внутри `static function` — это callback для ext-redis. В KPHP-режиме без ext-redis `RedisSubscriber` не используется.

> Подробнее — в [kphp-compatibility.md](./kphp-compatibility.md).

