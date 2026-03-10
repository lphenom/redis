# KPHP Compatibility

Пакет `lphenom/redis` полностью совместим с KPHP компиляцией.

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

## FfiRedisClient

В KPHP-режиме используется `FfiRedisClient` — заглушка, которая будет реализована через FFI bindings позже. Сейчас все методы бросают `NotImplementedException`.

## Ссылки

- [KPHP Documentation](https://vkcom.github.io/kphp/)
- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)

