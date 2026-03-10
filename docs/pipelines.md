# Pipeline

`RedisPipeline` — batch-выполнение команд Redis.

## Зачем нужен Pipeline

Каждый вызов Redis — это отдельный сетевой запрос. Pipeline позволяет буферизировать несколько команд и отправить их за одно обращение к серверу.

## Использование

```php
// Получить pipeline из клиента
$pipeline = $redis->pipeline();

// Буферизировать команды
$pipeline->set('key1', 'value1');
$pipeline->set('key2', 'value2', 3600);  // с TTL
$pipeline->incr('counter');

// Выполнить все команды за раз
$pipeline->execute();
```

## API

### `set(string $key, string $value, int $ttl = 0): void`

Буферизирует команду SET.

```php
$pipeline->set('session:123', serialize($data), 1800);
```

### `incr(string $key): void`

Буферизирует команду INCR.

```php
$pipeline->incr('page.views');
```

### `execute(): void`

Отправляет все буферизированные команды на сервер и сбрасывает буфер.

## KPHP-совместимость

`RedisPipeline`:
- Нет `callable` в типах
- Явное объявление свойств (нет constructor promotion)
- Нет `readonly`

```php
// build/kphp-entrypoint.php — pipeline работает в null-режиме (без ext-redis)
$pipeline = new RedisPipeline(null);
$pipeline->set('a', '1');
$pipeline->execute();
```

