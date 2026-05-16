# Technická dokumentace: Product Search

![Dashboard Screenshot](assets/images/dashboard-screenshot.jpg)

## 1. Obecný popis projektu

Product Search je webová aplikace pro vyhledávání a zobrazování produktů, postavená na principech **Domain-Driven Design (DDD)** s **Hexagonal Architecture (Ports & Adapters)**. Kódová báze je striktně rozdělena do čtyř izolovaných vrstev: `Domain`, `Application`, `Infrastructure`, `Presentation`. Křížové závislosti mezi vrstvami jsou zakázány – jediný směr závislostí: `Presentation → Application → Domain`, zatímco `Infrastructure` implementuje porty (rozhraní) z `Domain`.

Aplikace poskytuje:
- Vyhledání produktu podle UUID s přepínatelnými zdroji dat (MySQL / ElasticSearch).
- Cachování výsledků s přepínatelnými ovladači (soubor, Redis, null).
- Počítadlo zobrazení se synchronními (soubor, Redis) a asynchronními (Symfony Messenger + Redis transport) režimy.
- Dashboard s monitoringem stavu služeb, generováním testovacích dat a runtime přepínáním konfigurace.
- Lokalizaci rozhraní (angličtina / čeština) přes Symfony Translation Component s ICU formátem.
- Částečné aktualizace stránky přes Symfony UX Turbo bez ručního AJAX.

---

## 2. Technologický stack

### 2.1 Backend

| Komponent | Verze / Balík | Účel |
|-----------|---------------|------|
| PHP | `>=8.2` | Vývojový jazyk; `readonly` třídy, constructor property promotion, `match`, union types, nullsafe operator |
| Symfony Framework | `7.4.*` | DI kontejner, Router, Console, HttpKernel, Messenger, Serializer, Cache, Validator |
| `brick/money` | `^0.13.0` | Immutable reprezentace ceny v malých měnových jednotkách (CZK) |
| `elasticsearch/elasticsearch` | `^8.17` | Oficiální PHP klient pro ElasticSearch 8.x |
| `symfony/uid` | `7.4.*` | Validace UUID pro `ProductId` přes `Uuid::fromString()` |
| `symfony/messenger` + `symfony/redis-messenger` | `7.4.*` | Asynchronní fronta pro inkrementaci počítadla |
| `symfony/serializer` | `7.4.*` | Serializace DTO; ruční `toArray()` a `json_encode` v byznys logice jsou zakázány |
| `symfony/cache` | `7.4.*` | `FilesystemAdapter` a `RedisAdapter` pro cache a počítadlo |
| `symfony/filesystem` | `7.4.*` | Jediný povolený způsob zápisu souborů (kromě `.env.local` přes `dumpFile()`) |
| `fakerphp/faker` | `^1.24` | Generování testovacích dat (pouze dev) |
| `friendsofphp/php-cs-fixer` | `^3.95` | Autoformátování podle PSR-12 + Symfony pravidla |
| `phpstan/phpstan` + `phpstan-strict-rules` | `^2.1` / `^2.0` | Statická analýza, úroveň 6 |
| `phpunit/phpunit` | `^11.5` | Testování |

### 2.2 Frontend

| Komponent | Technologie | Poznámka |
|-----------|-------------|----------|
| Šablonovací systém | Twig (`symfony/twig-bundle`) | Všechny UI texty v šablonách v angličtině; překlad přes `\|trans` |
| CSS framework | Tailwind CSS (`symfonycasts/tailwind-bundle`) | Konfigurace přes `assets/styles/app.css` s `@import "tailwindcss"`; **samostatný soubor `tailwind.config.js` neexistuje** (Tailwind CSS v4) |
| Interaktivita | Alpine.js | Načítáno přes AssetMapper; dropdown, loading stavy, počítadlo zobrazení |
| Server-side rendering / částečné aktualizace | Symfony UX Turbo (`symfony/ux-turbo`) | `<turbo-frame id="search-result">` pro aktualizaci výsledků vyhledávání bez plného přenačtení stránky |
| Správa assetů | Symfony AssetMapper | Zakázány npm, yarn, webpack, vite |

### 2.3 Infrastruktura

| Služba | Obrázek | Účel |
|--------|---------|------|
| Nginx | `nginx:alpine` | Reverse proxy, port `8080:80` |
| PHP-FPM | Vlastní Dockerfile (`docker/php`) | Zpracování HTTP požadavků |
| PHP-CLI Worker | Vlastní Dockerfile | `messenger:consume async -vv` (vestavěný příkaz Symfony, nikoli vlastní) |
| MySQL | `mysql:8.0` | Port `3306:3306`, databáze `products` |
| ElasticSearch | `elasticsearch:8.17.0` | Single-node, bezpečnost vypnuta (`xpack.security.enabled=false`), port `9200:9200` |
| Redis | `redis:7-alpine` | Cache, počítadlo, fronta Messenger; port `6379:6379` |

---

## 3. Architektura

### 3.1 Směr závislostí

```
Presentation → Application → Domain ← Infrastructure
```

- **Domain** – čistý PHP, nezná Symfony, databáze, HTTP.
- **Application** – orchestrace use-case'ů, závisí pouze na Domain.
- **Infrastructure** – implementuje porty Domain vrstvy.
- **Presentation** – kontrolery, CLI příkazy, Twig šablony; závisí na Application.

### 3.2 Struktura projektu

```
src/
├── Domain/
│   ├── Contract/          # Porty (rozhraní): ProductSourceInterface, CacheInterface, CounterInterface, ConfigInterface, HealthCheckInterface, SeederInterface, SyncCounterInterface, IElasticSearchDriver, IMysqlDriver
│   ├── DTO/               # Data Transfer Objects: ProductDTO
│   ├── Exception/         # Domain výjimky: InvalidProductIdException, ProductNotFoundException, SourceUnavailableException, CacheException, CounterException, QueueException
│   └── ValueObject/       # Immutable Value Objects: ProductId, Price
├── Application/
│   ├── DTO/               # ProductWithTraceDTO
│   └── Service/           # Orchestrace use-case: ProductService, DashboardManager
├── Infrastructure/
│   ├── Adapter/           # ElasticSearchProductAdapter, MySqlProductAdapter
│   ├── Cache/             # FileCacheAdapter, RedisCacheAdapter, NullCacheAdapter
│   ├── Command/           # MigrateCommand, ElasticSearchInitCommand, SeedCommand
│   ├── Config/            # EnvConfig
│   ├── Counter/           # FilesystemCounter, RedisCounter, NullCounter
│   ├── Decorator/         # AsyncCounterDecorator
│   ├── Driver/            # SimpleElasticSearchDriver, SimpleMySqlDriver
│   ├── Factory/           # ProductSourceFactory, CacheAdapterFactory, CounterAdapterFactory, PdoConnectionFactory, RedisConnectionFactory
│   ├── Health/            # MySqlHealthAdapter, ElasticSearchHealthAdapter, RedisHealthAdapter
│   ├── Message/           # CounterIncrementMessage
│   ├── MessageHandler/    # CounterIncrementHandler
│   ├── Normalizer/        # PriceNormalizer, ProductIdNormalizer
│   ├── Seeder/            # ProductSeeder, SeederAdapter
│   └── Service/           # DataSeederService
└── Presentation/
    ├── Controller/        # DashboardController, ProductController, LocaleController
    ├── EventSubscriber/   # LocaleSubscriber
    └── Handler/           # ExceptionSubscriber
templates/                 # Twig šablony
assets/                    # Frontend: JS (AssetMapper), CSS (Tailwind)
tests/                     # PHPUnit: Unit (8), Integration (14), Concurrent (2), Functional (2)
docker/                    # Dockerfiles, Nginx konfigurace
storage/cache/             # Adresář pro file cache (zapisovatelný)
storage/counter/           # Adresář pro file counter (zapisovatelný)
```

---

## 4. Domain vrstva (`src/Domain/`)

### 4.1 Value Objects

#### `ProductId` (`src/Domain/ValueObject/ProductId.php:16`)
- Immutable `readonly` třída.
- Obaluje `Symfony\Component\Uid\Uuid`.
- Privátní konstruktor; vytváření pouze přes `fromString(string $value): self`.
- Vyhazuje `InvalidProductIdException` při neplatném UUID.
- Metoda `value(): string` vrací řetězec ve formátu RFC 4122.
- Metoda `__toString(): string` deleguje na `value()`.

#### `Price` (`src/Domain/ValueObject/Price.php:15`)
- Immutable `readonly` třída.
- Výchozí měna – `CZK` (konstanta `DEFAULT_CURRENCY = 'CZK'`).
- Vnitřní reprezentace – `Brick\Money\Money`, vytvořené přes `Money::ofMinor()`.
- Factory metoda `of(string $amount): self` přijímá částku v malých jednotkách (např. `"19990"` pro 199,90 CZK).
- `formatted(): string` formátuje cenu podle lokality `cs_CZ`.
- `amount(): string` vrací částku v malých jednotkách jako řetězec.
- `currency(): string` vrací kód měny.
- `toMoney(): Money` vrací vnitřní objekt `brick/money`.

### 4.2 Data Transfer Objects

#### `ProductDTO` (`src/Domain/DTO/ProductDTO.php:16`)
- `final readonly` třída s constructor property promotion.
- Pole: `ProductId $id`, `string $name`, `Price $price`, `string $description`.
- Statická factory metoda `fromArray(array $data): self` s validací přítomnosti klíčů (`id`, `name`, `price`, `description`). Při chybějícím klíči vyhazuje `\InvalidArgumentException`.

### 4.3 Porty (Contracts / Interfaces)

Všechna rozhraní jsou umístěna v `src/Domain/Contract/`:

| Rozhraní | Soubor | Účel |
|----------|--------|------|
| `ProductSourceInterface` | `ProductSourceInterface.php:15` | `findById(ProductId): ProductDTO`, `findSampleIds(int): list<string>` |
| `CacheInterface` | `CacheInterface.php:14` | `get(string): ?ProductDTO`, `set(string, ProductDTO, ?int): void`, `delete(string): void` |
| `CounterInterface` | `CounterInterface.php:14` | `increment(ProductId): void`, `getCount(ProductId): int` |
| `SyncCounterInterface` | `SyncCounterInterface.php:14` | Marker-rozhraní, rozšiřuje `CounterInterface`; zabraňuje rekurzivní dispatche v `CounterIncrementHandler` |
| `ConfigInterface` | `ConfigInterface.php:12` | `getString()`, `getInt()`, `getBool()`, `has()`, `getDataSource()`, `getCacheDriver()`, `getCounterMode()` |
| `HealthCheckInterface` | `HealthCheckInterface.php:13` | `isHealthy(): bool` |
| `SeederInterface` | `SeederInterface.php:10` | `seed(int): void` |
| `IElasticSearchDriver` | `IElasticSearchDriver.php:12` | `findById(string): array`, `search(array): array` |
| `IMysqlDriver` | `IMysqlDriver.php:12` | `findProduct(string): array`, `findAllIds(int): list<string>` |

### 4.4 Domain výjimky

Všechny výjimky jsou `final` třídy, dědící standardní PHP výjimky (`src/Domain/Exception/`):
- `InvalidProductIdException` – `\InvalidArgumentException`
- `ProductNotFoundException` – `\RuntimeException`
- `SourceUnavailableException` – `\RuntimeException`
- `CacheException` – `\RuntimeException`
- `CounterException` – `\RuntimeException`
- `QueueException` – `\RuntimeException`

---

## 5. Application vrstva (`src/Application/`)

### 5.1 `ProductService` (`src/Application/Service/ProductService.php:21`)

Orchestruje scénář získání produktu:
1. Inkrementuje počítadlo (`$counter->increment($id)`).
2. Kontroluje cache (`$cache->get('product_'.$id->value())`).
3. Při cache miss – dotáže se zdroje (`$source->findById($id)`).
4. Zapíše do cache (`$cache->set(...)`, TTL = `null` – trvalé uložení, dle zadání).

Metody:
- `getProduct(ProductId $id): ProductDTO` – standardní tok.
- `getCount(ProductId $id): int` – aktuální hodnota počítadla.
- `getSampleProductIds(int $limit): list<string>` – deleguje na `$source->findSampleIds($limit)`.
- `getProductWithTrace(ProductId $id): ProductWithTraceDTO` – rozšířený tok s execution log (cache hit/miss, zdroj, kroky), ale **bez TTFB** – TTFB se měří v kontroleru (Presentation vrstva).

### 5.2 `DashboardManager` (`src/Application/Service/DashboardManager.php:16`)

Správa runtime konfigurace:
- `setToggle(string $key, string $value): void` – čte `.env.local` přes `Symfony\Component\Filesystem\Filesystem::readFile()`, aktualizuje nebo přidá proměnnou, přepíše soubor přes `Filesystem::dumpFile()` (`src/Application/Service/DashboardManager.php:78`).
- `getCurrentConfig(): array` – vrací aktuální zdroj, cache a režim počítadla.
- `getHealthStatus(): array` – kontroluje MySQL, ElasticSearch, Redis přes `HealthCheckInterface`.
- `getSampleProductIds(int $limit): list<string>` – deleguje na `ProductSourceInterface`.
- `seed(int $count): void` – deleguje na `SeederInterface`.

### 5.3 `ProductWithTraceDTO` (`src/Application/DTO/ProductWithTraceDTO.php:19`)

`final readonly` třída:
- `ProductDTO $product`
- `bool $cacheHit`
- `string $source`
- `list<string> $executionLog`

---

## 6. Infrastructure vrstva (`src/Infrastructure/`)

### 6.1 Adaptéry pro ProductSource

#### `ElasticSearchProductAdapter` (`src/Infrastructure/Adapter/ElasticSearchProductAdapter.php:17`)
- Implementuje `ProductSourceInterface`.
- Deleguje na `IElasticSearchDriver::findById()` a `search()`.
- Index – konstanta `INDEX_NAME = 'products'`.
- `findSampleIds` používá `match_all` s `_source: false`.

#### `MySqlProductAdapter` (`src/Infrastructure/Adapter/MySqlProductAdapter.php:17`)
- Implementuje `ProductSourceInterface`.
- Deleguje na `IMysqlDriver::findProduct()` a `findAllIds()`.

### 6.2 Drivery

#### `SimpleElasticSearchDriver` (`src/Infrastructure/Driver/SimpleElasticSearchDriver.php:16`)
- Implementuje `IElasticSearchDriver`.
- Injektuje `Elastic\Elasticsearch\Client`.
- `findById`: provede `$client->get(['index' => 'products', 'id' => $id])`.
- Zpracovává `ClientResponseException` s kódem 404 → `ProductNotFoundException`; ostatní → `SourceUnavailableException`.
- `search`: provede `$client->search($params)->asArray()`.

#### `SimpleMySqlDriver` (`src/Infrastructure/Driver/SimpleMySqlDriver.php:14`)
- Implementuje `IMysqlDriver`.
- Injektuje `\PDO`.
- `findProduct`: `SELECT id, name, price, description FROM products WHERE id = :id`.
- `findAllIds`: `SELECT id FROM products LIMIT :limit` s parametrem `PDO::PARAM_INT`.
- `\PDOException` se zachytávají a obalují do `SourceUnavailableException`.

### 6.3 Cache adaptéry

**Důležité:** Všechny souborové operace cache se provádějí **výhradně** přes `Symfony\Component\Cache\Adapter\FilesystemAdapter`. Raw PHP file I/O (`fopen`, `flock`, `ftruncate`, `file_get_contents` atd.) je **zakázáno**. `FilesystemAdapter` sám spravuje adresář `storage/cache/`.

#### `FileCacheAdapter` (`src/Infrastructure/Cache/FileCacheAdapter.php:17`)
- Implementuje `CacheInterface`.
- Používá `Symfony\Component\Cache\Adapter\FilesystemAdapter`.
- Serializace / deserializace přes injektovaný `NormalizerInterface&DenormalizerInterface` (`symfony/serializer`).
- `set` volá `expiresAfter($ttl)`, kde `null` znamená trvalé uložení (permanent cache).

#### `RedisCacheAdapter` (`src/Infrastructure/Cache/RedisCacheAdapter.php:17`)
- Identický s `FileCacheAdapter`, ale používá `RedisAdapter`.

#### `NullCacheAdapter` (`src/Infrastructure/Cache/NullCacheAdapter.php:15`)
- No-op implementace: `get` vždy vrací `null`, `set` a `delete` jsou prázdné.

### 6.4 Counter adaptéry

#### `FilesystemCounter` (`src/Infrastructure/Counter/FilesystemCounter.php:18`)
- Implementuje `CounterInterface` a `SyncCounterInterface`.
- Používá `FilesystemAdapter` s namespace `counter`.
- Prefix klíče: `counter_`.
- `increment`: načte aktuální hodnotu (`getItem` → `isHit` ? `(int) $item->get()` : `0`), zvýší o 1, uloží s `expiresAfter(null)` (permanent).
- `getCount`: vrací `0` při cache miss.

#### `RedisCounter` (`src/Infrastructure/Counter/RedisCounter.php:15`)
- Implementuje `CounterInterface` a `SyncCounterInterface`.
- Používá nativní příkaz `\Redis::incr()` (atomický).
- Prefix klíče: `counter:`.

#### `NullCounter` (`src/Infrastructure/Counter/NullCounter.php:16`)
- No-op: `increment` je prázdný, `getCount` vrací `0`.

### 6.5 Async Counter Dekorátor

#### `AsyncCounterDecorator` (`src/Infrastructure/Decorator/AsyncCounterDecorator.php:20`)
- Implementuje `CounterInterface`.
- Injektuje `CounterInterface $fallbackCounter`, `MessageBusInterface`, `LoggerInterface`.
- `increment`: dispečerizuje `CounterIncrementMessage` do sběrnice Messenger (`async` transport).
- Při chybě `Symfony\Component\Messenger\Exception\ExceptionInterface` loguje warning a provede **fallback** na synchronní počítadlo (`$fallbackCounter->increment($id)`).
- `getCount` deleguje na fallback.

#### `CounterIncrementMessage` (`src/Infrastructure/Message/CounterIncrementMessage.php:13`)
- `final readonly` s polem `string $productId`.
- Serializovatelný payload pro frontu.

#### `CounterIncrementHandler` (`src/Infrastructure/MessageHandler/CounterIncrementHandler.php:18`)
- `#[AsMessageHandler]`.
- Injektuje `SyncCounterInterface` (nikoli `CounterInterface`, aby se předešlo rekurzi).
- Volá `$counter->increment(ProductId::fromString($message->productId()))`.

### 6.6 Továrny (Factories)

#### `ProductSourceFactory` (`src/Infrastructure/Factory/ProductSourceFactory.php:19`)
- Vytváří `ElasticSearchProductAdapter` nebo `MySqlProductAdapter` na základě `ConfigInterface::getDataSource()` přes `match`.

#### `CacheAdapterFactory` (`src/Infrastructure/Factory/CacheAdapterFactory.php:21`)
- Vytváří `FileCacheAdapter`, `RedisCacheAdapter` nebo `NullCacheAdapter`.
- Pro souborový a Redis cache vytváří odpovídající Symfony adaptér (`FilesystemAdapter`/`RedisAdapter`) a předává serializátor.

#### `CounterAdapterFactory` (`src/Infrastructure/Factory/CounterAdapterFactory.php:21`)
- `create(string $mode): CounterInterface` – pro režim `async` obaluje sync-counter do `AsyncCounterDecorator`.
- `createSync(string $mode): CounterInterface` – vytváří pouze synchronní počítadlo (soubor, Redis, null). Pokud `$mode == 'async'`, rozlišuje podle `COUNTER_BASE_MODE`.

#### `PdoConnectionFactory` (`src/Infrastructure/Factory/PdoConnectionFactory.php:12`)
- `final readonly` třída se statickou metodou `create(string $databaseUrl): \PDO`.
- Parsovává Symfony-stylovou URL (`mysql://root:secret@mysql:3306/products`) přes `parse_url()`.
- Zapíná `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES = false`.

#### `RedisConnectionFactory` (`src/Infrastructure/Factory/RedisConnectionFactory.php:10`)
- `final` třída se statickou metodou `create(string $dsn): \Redis`.
- Parsovává DSN a volá `connect($host, $port)`.

### 6.7 Health Check adaptéry

- **`MySqlHealthAdapter`** (`src/Infrastructure/Health/MySqlHealthAdapter.php:12`): provede `SELECT 1` přes PDO.
- **`ElasticSearchHealthAdapter`** (`src/Infrastructure/Health/ElasticSearchHealthAdapter.php:13`): volá `$client->info()`.
- **`RedisHealthAdapter`** (`src/Infrastructure/Health/RedisHealthAdapter.php:12`): volá `$redis->ping()`, kontroluje `true` nebo `'PONG'`.
- **Důležitý detail:** konstruktor přijímá `?\Redis $redis = null` (nullable). Pokud Redis není nakonfigurován, `isHealthy()` vrátí `false` (graceful degradation), místo aby spadl s chybou.

### 6.8 Normalizátory

#### `PriceNormalizer` (`src/Infrastructure/Normalizer/PriceNormalizer.php:16`)
- Implementuje `NormalizerInterface` + `DenormalizerInterface`.
- Pro formát `json` vrací `int` (minor amount); jinak – řetězec z `formatted()`.

#### `ProductIdNormalizer` (`src/Infrastructure/Normalizer/ProductIdNormalizer.php:16`)
- Implementuje `NormalizerInterface` + `DenormalizerInterface`.
- Vrací `value()` (UUID řetězec).

### 6.9 CLI příkazy

| Příkaz | Soubor | Popis |
|--------|--------|-------|
| `app:migrate` | `src/Infrastructure/Command/MigrateCommand.php:14` | Vytvoří tabulku `products` v MySQL (DDL) přes `PDO::exec()` |
| `app:es:init` | `src/Infrastructure/Command/ElasticSearchInitCommand.php:16` | Vytvoří index `products` s mappingem v ES. Přepínač `--force` pro přeuložení indexu |
| `app:seed` | `src/Infrastructure/Command/SeedCommand.php:18` | Generuje falešné produkty přes `ProductSeeder`, zapisuje do MySQL a ES přes `DataSeederService` s progress barem. Přepínač `--count` (výchozí `SEED_COUNT` z env) |

**Důležité:** V projektu **neexistuje** vlastní příkaz `app:worker:start`. Worker se spouští standardním příkazem Symfony Messenger: `php bin/console messenger:consume async -vv`, který je definován v `docker-compose.yml` pro službu `worker`.

### 6.10 Seeder

#### `ProductSeeder` (`src/Infrastructure/Seeder/ProductSeeder.php:16`)
- Generuje `ProductDTO` pomocí Faker: UUID v4 (`Symfony\Component\Uid\Uuid::v4()->toRfc4122()`), 3 slova pro název, cena od 10000 do 1000000 minor units, odstavec popisu.
- Metoda `generate(int $count): list<ProductDTO>`.

#### `DataSeederService` (`src/Infrastructure/Service/DataSeederService.php:14`)
- Zapisuje data dávkově (batch insert) do MySQL (`MYSQL_BATCH_SIZE = 100`) a ElasticSearch (`ES_BULK_BATCH_SIZE = 200`).
- MySQL: víceřádkový `INSERT ... VALUES (...), (...)` uvnitř transakce.
- ElasticSearch: bulk API.
- Podporuje callback `onChunk` pro progress bar.

#### `SeederAdapter` (`src/Infrastructure/Seeder/SeederAdapter.php:13`)
- Implementuje doménový port `SeederInterface`.
- Bridge / Adapter: spojuje `ProductSeeder` (generování) a `DataSeederService` (perzistence).
- `seed(int $count): void` volá `$productSeeder->generate($count)` a `$dataSeederService->seed($products)`.

### 6.11 Config Adapter

#### `EnvConfig` (`src/Infrastructure/Config/EnvConfig.php:15`)
- Implementuje `ConfigInterface`.
- Čte z `Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface`.
- `getDataSource()`, `getCacheDriver()`, `getCounterMode()` čtou parametry `app.active_*`.

---

## 7. Presentation vrstva (`src/Presentation/`)

### 7.1 Kontrolery

#### `DashboardController` (`src/Presentation/Controller/DashboardController.php:15`)
- `GET /` (`dashboard`) – renderuje `dashboard/index.html.twig` se stavem zdraví, aktuální konfigurací, ukázkovými ID produktů a seznamem povolených toggle hodnot.
- `POST /toggle` (`dashboard_toggle`) – validace CSRF (`isCsrfTokenValid('toggle', ...)`), kontrola povolených klíčů/hodnot podle bílé listiny (`ALLOWED_TOGGLES`), volání `DashboardManager::setToggle()`. Při AJAX vrací JSON s oznámením o nutnosti `cache:clear` a restartu workeru.
- `POST /seed` (`dashboard_seed`) – validace CSRF, rozsah count [1, 1000], volání `DashboardManager::seed()`, flash-message, redirect.

**Bílá listina přepínačů (`ALLOWED_TOGGLES`):**
- `ACTIVE_PRODUCT_SOURCE`: `elasticsearch`, `mysql`
- `ACTIVE_CACHE_DRIVER`: `file`, `redis`, `null`
- `ACTIVE_COUNTER_MODE`: `async`, `filesystem`, `redis`, `null`

#### `ProductController` (`src/Presentation/Controller/ProductController.php:16`)
- `GET /product/{id}` (`product_detail`) – vytvoří `ProductId::fromString($id)`, volá `ProductService::getProductWithTrace()`, měří TTFB (`microtime(true)`).
  - **Optimistický counter display:** Pokud `ACTIVE_COUNTER_MODE` === `'async'`, kontroler spočítá `$displayCount = $actualCount + 1` (`src/Presentation/Controller/ProductController.php:38`), protože async increment ještě není zpracován workerem, ale UI má ukázat uživateli aktuální (předběžnou) hodnotu.
  - Pokud požadavek přichází přes Turbo Frame (`$request->headers->has('Turbo-Frame')`) – renderuje `product/_frame.html.twig` s `$displayCount`.
  - Jinak – serializuje `ProductDTO` do JSON přes `SerializerInterface`.
- `GET /product/{id}/counter` (`product_counter`) – vrací JSON `{"count": N}` se skutečnou hodnotou z `$this->productService->getCount($productId)` (bez optimistického inkrementu).

#### `LocaleController` (`src/Presentation/Controller/LocaleController.php:18`)
- `GET /locale/{locale}` (`locale_switch`) – povolené lokality: `cs`, `en`. Uloží `_locale` do session a přesměruje na referer nebo dashboard.

### 7.2 Odběratelé událostí (Event Subscribers)

#### `LocaleSubscriber` (`src/Presentation/EventSubscriber/LocaleSubscriber.php:15`)
- Priorita 17 (mezi SessionListener 128 a LocaleListener 16).
- Obnoví lokalitu ze session (`_locale`) při každém požadavku.

#### `ExceptionSubscriber` (`src/Presentation/Handler/ExceptionSubscriber.php:18`)
- `#[AsEventListener(event: 'kernel.exception', priority: 100)]`.
- Funguje pouze pro JSON požadavky (`Accept: application/json` nebo `_format: json`).
- Mapování domain výjimek na HTTP statusy:
  - `InvalidProductIdException` → 400
  - `ProductNotFoundException` → 404
  - `SourceUnavailableException`, `CacheException`, `CounterException`, `QueueException` → 503
  - Ostatní → 500

---

## 8. Symfony konfigurace (`config/`)

### 8.1 `services.yaml` (`config/services.yaml`)

Klíčové DI nastavení:
- **Drivery:**
  - `PDO` – továrna `PdoConnectionFactory::create(DATABASE_URL)` (`config/services.yaml:37`).
  - `Elastic\Elasticsearch\Client` – `ClientBuilder::fromConfig(['hosts' => [ELASTICSEARCH_URL]])` (`config/services.yaml:44`).
  - `Redis` – továrna `RedisConnectionFactory::create(REDIS_URL)` (`config/services.yaml:49`).
- **Health checks:** aliasy pro MySQL, ES, Redis adaptéry (`config/services.yaml:57`).
- **Domain Porty:**
  - `ConfigInterface` → `EnvConfig` (`config/services.yaml:93`).
  - `IElasticSearchDriver` → `SimpleElasticSearchDriver` (`config/services.yaml:96`).
  - `IMysqlDriver` → `SimpleMySqlDriver` (`config/services.yaml:101`).
  - `ProductSourceInterface` → továrna `ProductSourceFactory::create()` (`config/services.yaml:104`).
  - `CacheInterface` → továrna `CacheAdapterFactory::create(active_cache_driver)` (`config/services.yaml:107`).
  - `CounterInterface` → továrna `CounterAdapterFactory::create(active_counter_mode)` (`config/services.yaml:112`).
  - `SyncCounterInterface` → továrna `CounterAdapterFactory::createSync(active_counter_mode)` (`config/services.yaml:117`).
  - `SeederInterface` → `SeederAdapter` (`config/services.yaml:122`).

### 8.2 Messenger (`config/packages/messenger.yaml`)

- Transport: `async` → Redis (`MESSENGER_TRANSPORT_DSN`)
- Routing: `App\Infrastructure\Message\CounterIncrementMessage` → `async`
- Retry strategy: max 3, delay 1000ms, multiplier 2, max delay 30000ms

### 8.3 Prostředí (`.env`)

Výchozí hodnoty (`/home/gun/logio/.env`):
```
ACTIVE_PRODUCT_SOURCE=elasticsearch
ACTIVE_CACHE_DRIVER=file
ACTIVE_COUNTER_MODE=async
COUNTER_BASE_MODE=filesystem
CACHE_TTL=3600
SEED_COUNT=1000
```

Runtime přepínání se provádí zápisem do `.env.local` přes `DashboardManager::setToggle()`.

### 8.4 Databáze

**Tabulka `products` (vytváří se přes `app:migrate`):**
```sql
CREATE TABLE IF NOT EXISTS products (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price BIGINT NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

**ElasticSearch index `products` (vytváří se přes `app:es:init`):**
```json
{
  "mappings": {
    "properties": {
      "id": { "type": "keyword" },
      "name": { "type": "text" },
      "price": { "type": "long" },
      "description": { "type": "text" }
    }
  }
}
```

---

## 9. Frontend

### 9.1 AssetMapper

Vstupní bod: `assets/app.js`
- Importuje Stimulus bootstrap, Tailwind CSS, Alpine.js.
- `window.Alpine = Alpine; Alpine.start();`

### 9.2 Tailwind CSS

Konfigurace přes `assets/styles/app.css`:
```css
@import "tailwindcss";
@source "../../templates/**/*.twig";
@source "../**/*.js";
```

Používá se **Tailwind CSS v4** syntaxe. **Samostatný soubor `tailwind.config.js` neexistuje** – konfigurace je vestavěná v CSS.

### 9.3 Twig šablony

- **`base.html.twig`** (`templates/base.html.twig`): základní layout s připojením `importmap('app')`.
- **`dashboard/index.html.twig`** (`templates/dashboard/index.html.twig`): Alpine.js data (`searchId`, `dropdownOpen`, `seeding`). Sekce: Control Panel, Search (dropdown s sample IDs), Search Result (`<turbo-frame id="search-result">`). Přepínání lokality přes odkazy s `data-turbo="false"`.
- **`product/_frame.html.twig`** (`templates/product/_frame.html.twig`): levý sloupec – karta produktu (název, cena, UUID, popis, počítadlo zobrazení inicializované hodnotou `initialCount` z kontroleru přes Alpine.js). Pravý sloupec – terminálový trace view (TTFB, cache hit/miss, execution log).
- **`macros/ui.html.twig`** (`templates/macros/ui.html.twig`): makro `led(healthy)` – 3D skleněný SVG indikátor (zelený / červený) s `radialGradient`.

---

## 10. Docker & Infrastruktura

### 10.1 Docker Compose (`docker-compose.yml`)

**Služby a závislosti:**
```
nginx → app (healthy)
app → mysql (healthy)
    → elasticsearch (healthy)
    → redis (healthy)
worker → mysql (healthy)
       → elasticsearch (healthy)
       → redis (healthy)
```

**Worker:**
- Služba `worker` používá stejný Dockerfile jako `app`.
- Spouštěcí příkaz: `php bin/console messenger:consume async -vv`.
- **Neexistuje** vlastní příkaz `app:worker:start`.

**Health checks:**
- nginx: `curl -f http://localhost/`
- app: `php-fpm -t`
- worker: `php -v`
- mysql: `mysqladmin ping`
- elasticsearch: `curl http://localhost:9200/_cluster/health`
- redis: `redis-cli ping`

### 10.2 Dockerfile (`docker/php/Dockerfile`)

- Base: `php:8.2-fpm`
- Rozšíření: `pdo_mysql`, `bcmath`, `opcache`, `pcntl`, `intl`, `zip`, `gd`, `redis` (PECL)
- Lokalita: `cs_CZ.UTF-8`
- Composer: z oficiálního obrazu
- Pracovní adresář: `/var/www`

### 10.3 Oprávnění souborového systému

Adresáře `storage/cache/` a `storage/counter/` musí být zapisovatelné uvnitř kontejneru (uživatel `www-data`). `FilesystemAdapter` sám spravuje svou strukturu podadresářů.

---

## 11. Caching & Counter

### 11.1 Striktní požadavek: žádné ruční souborové operace

Všechny operace cache, počítadla a souborového systému se provádějí **výhradně** přes Symfony komponenty. Následující PHP funkce jsou **zakázány** v application code: `fopen`, `fclose`, `fread`, `fwrite`, `flock`, `ftruncate`, `rewind`, `fflush`, `file_get_contents`, `file_put_contents`, `stream_get_contents`.

Jediná povolená operace zápisu souboru: `Symfony\Component\Filesystem\Filesystem::dumpFile()` pro `.env.local`.

### 11.2 File Cache
- `Symfony\Component\Cache\Adapter\FilesystemAdapter` s namespace `cache`.
- TTL se řídí přes `CacheItemInterface::expiresAfter()`. `expiresAfter(null)` znamená permanent / infinite cache.
- Adaptér sám spravuje adresář `storage/cache/`.

### 11.3 File Counter
- `Symfony\Component\Cache\Adapter\FilesystemAdapter` s namespace `counter`.
- Klíč: `counter_{uuid}`. Hodnota: integer count.
- `increment`: fetch přes `getItem()`, vypočítá `current + 1`, `set()`, `save()`. Chybějící klíč → `0`.
- `getCount`: fetch přes `getItem()`, vrací `0` při cache miss.

### 11.4 Redis Counter
- Atomická operace `\Redis::incr()`.
- Klíč: `counter:{uuid}`.

### 11.5 Asynchronní Counter (Symfony Messenger)

Když `ACTIVE_COUNTER_MODE=async`:
1. `ProductService` volá `$counter->increment($id)`.
2. `CounterAdapterFactory::create('async')` vrací `AsyncCounterDecorator`, obalený kolem sync-counter (soubor nebo Redis).
3. `AsyncCounterDecorator::increment()` dispečerizuje `CounterIncrementMessage` přes `MessageBusInterface` do transportu `async` (Redis).
4. Při chybě dispečerizace (`Symfony\Component\Messenger\Exception\ExceptionInterface`) – **fallback** na synchronní inkrement přes `$fallbackCounter->increment($id)`.
5. Worker (`messenger:consume async`) obdrží zprávu.
6. `CounterIncrementHandler` injektuje `SyncCounterInterface` (garantovaně ne async-decorated) a volá `$counter->increment()`.

**Důležité:** Graceful shutdown workeru je automaticky řešen Symfony Messenger (SIGTERM / SIGINT).

---

## 12. Testování

### 12.1 Struktura testů

| Úroveň | Adresář | Počet souborů |
|--------|---------|---------------|
| Unit | `tests/Unit/` | **8** |
| Integration | `tests/Integration/` | 14 |
| Concurrent | `tests/Concurrent/` | 2 |
| Functional | `tests/Functional/` | 2 |
| **Celkem** | | **26** |

**Unit testy (8 souborů):**
- `tests/Unit/Application/Service/DashboardManagerTest.php`
- `tests/Unit/Application/Service/ProductServiceTest.php`
- `tests/Unit/Domain/DTO/ProductDTOTest.php`
- `tests/Unit/Domain/ValueObject/PriceTest.php`
- `tests/Unit/Domain/ValueObject/ProductIdTest.php`
- `tests/Unit/Infrastructure/Config/EnvConfigTest.php`
- `tests/Unit/Infrastructure/Decorator/AsyncCounterDecoratorTest.php`
- `tests/Unit/Infrastructure/Seeder/ProductSeederTest.php`

### 12.2 Příklady testů

**Unit: `ProductServiceTest`** (`tests/Unit/Application/Service/ProductServiceTest.php:19`)
- Kontroluje pořadí volání: nejprve `counter->increment`, pak `cache->get`.
- Cache hit: `source->findById` se nevolá.
- Cache miss: čtení ze source a zápis do cache.

**Unit: `AsyncCounterDecoratorTest`** (`tests/Unit/Infrastructure/Decorator/AsyncCounterDecoratorTest.php:20`)
- Kontroluje dispečerizaci `CounterIncrementMessage` přes `MessageBusInterface`.
- Kontroluje fallback na sync counter při chybě sběrnice.

**Integration: `MySqlProductAdapterTest`** (`tests/Integration/Infrastructure/Adapter/MySqlProductAdapterTest.php:16`)
- Pracuje s reálným MySQL v Dockeru.
- Kontroluje `findById`, `findSampleIds`, `ProductNotFoundException`.

**Concurrent: `RedisCounterConcurrentTest`** (`tests/Concurrent/Infrastructure/Counter/RedisCounterConcurrentTest.php:14`)
- 10 forků, každý provede 10 inkrementací.
- Očekává výslednou hodnotu `100` (kontrola atomičnosti `Redis::incr`).

**Functional: `ProductEndpointTest`** (`tests/Functional/Presentation/Controller/ProductEndpointTest.php:14`)
- Vytvoří klienta, vloží testovací data do MySQL (`REPLACE INTO`) a ES (`index` + `refresh`).
- Kontroluje `GET /product/{id}` → 200 + JSON struktura.
- Kontroluje 404 pro neexistující produkt a 400 pro neplatné UUID.
- Kontroluje inkrement počítadla: volá `messenger:consume async --limit 10 --time-limit 1` synchronně v testu.

### 12.3 Bootstrap

`tests/bootstrap.php` (`tests/bootstrap.php`):
- Nastaví `APP_ENV=test`.
- Vyčistí a přeuloží `storage/cache` a `storage/counter` přes `Symfony\Component\Filesystem\Filesystem`.

---

## 13. Nástroje kvality kódu

### 13.1 PHPStan (`phpstan.neon`)

- Úroveň: **6**.
- Připojeny `phpstan-strict-rules`.
- Cesty: `src`, `tests`.
- Výjimka: `src/Kernel.php`.

### 13.2 php-cs-fixer (`.php-cs-fixer.dist.php`)

- Pravidla: `@PSR12`, `@Symfony`, `declare_strict_types`, `single_quote`, `trailing_comma_in_multiline`.
- Rozsah: `src/`, `tests/`.

### 13.3 Makefile

| Cíl | Příkaz |
|-----|--------|
| `up` | `docker compose up -d --build` |
| `down` | `docker compose down` |
| `app` | `docker compose exec app bash` |
| `test` | `docker compose exec -e APP_ENV=test app php bin/phpunit` |
| `stan` | `docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M` |
| `fix` | `docker compose exec app ./vendor/bin/php-cs-fixer fix` |
| `seed` | `docker compose exec app php bin/console app:seed` |
| `build-assets` | `tailwind:build` + `asset-map:compile` |

**Důležité:** Příkaz v Makefile je `asset-map:compile` (s pomlčkou), nikoli `assetmap:compile`.

---

## 14. Lokalizace

Implementována přes Symfony Translation Component s ICU Message Format.
- Soubory: `translations/messages+intl-icu.en.yaml`, `translations/messages+intl-icu.cs.yaml`.
- Podporovány ICU plural forms (`{count, plural, one {# product} other {# products}}`).
- Lokalita je uložena v session (`_locale`), obnovuje ji `LocaleSubscriber`.
- CLI příkazy také podporují překlad (přes chráněnou metodu `trans()` s fallbackem na raw key).

---

## 15. Toky dat (Use Cases)

### 15.1 Vyhledání produktu (HTTP JSON)

1. `ProductController::detail()` obdrží `string $id`.
2. Vytvoří `ProductId::fromString($id)` (validace UUID).
3. Měří TTFB: `$startTime = microtime(true)`.
4. Volá `ProductService::getProductWithTrace($productId)`.
5. `ProductService`:
   - `counter->increment($id)` (async dekorátor → dispatch do Redis fronty).
   - `cache->get('product_' . $id->value())`.
   - Cache hit → vrací `ProductWithTraceDTO`.
   - Cache miss → `source->findById($id)` (MySQL nebo ES) → `cache->set(...)`.
6. Kontroler serializuje `ProductDTO` do JSON přes `SerializerInterface`.
7. `ExceptionSubscriber` zachytává domain výjimky a vrací JSON s správným HTTP statusem.

### 15.2 Vyhledání produktu (Turbo Frame)

1. Stejná kontrolerská metoda, ale požadavek obsahuje hlavičku `Turbo-Frame`.
2. Kontroler určí `$isAsync = $config->getCounterMode() === 'async'`.
3. Pro async režim vypočítá optimistický count: `$displayCount = $actualCount + 1`.
4. Renderuje `product/_frame.html.twig` s `initialCount = $displayCount`.
5. `<turbo-frame id="search-result">` se aktualizuje na stránce bez plného přenačtení.

### 15.3 Přepnutí konfigurace

1. Dashboard odesílá POST na `/toggle` s CSRF tokenem.
2. `DashboardController` validuje klíč a hodnotu podle bílé listiny (`ALLOWED_TOGGLES`).
3. `DashboardManager::setToggle()` aktualizuje `.env.local`.
4. Uživateli se zobrazí notice o nutnosti `cache:clear` a restartu workeru (protože env proměnné se čtou při startu kontejneru / Symfony kernelu, runtime přepnutí přes `.env.local` vyžaduje přečtení).

---

## 16. Závislosti a DI

V projektu se používá **výhradně konstruktorové vkládání závislostí** (constructor injection). Přímé vytváření objektů přes `new` v byznys logice je zakázáno; továrny se používají pouze v DI konfiguraci (`services.yaml`).

Klíčové vazby:
- `ProductService` závisí na `ProductSourceInterface`, `CacheInterface`, `CounterInterface`, `ConfigInterface`.
- `DashboardManager` závisí na `ConfigInterface`, `ProductSourceInterface`, `HealthCheckInterface` (×3), `SeederInterface`, `Filesystem`, `$projectDir`.
- `ProductSourceFactory` závisí na `ConfigInterface`, `IElasticSearchDriver`, `IMysqlDriver`.
- `CounterAdapterFactory` závisí na `MessageBusInterface`, `\Redis`, `$counterDir`, `LoggerInterface`, `$counterBaseMode`.
- `AsyncCounterDecorator` závisí na `CounterInterface $fallbackCounter` (sync), `MessageBusInterface`, `LoggerInterface`.

---

## 17. Architektonická pozorování a disclaimer

1. **Striktnost vrstev je dodržena:** Domain nezná Symfony, PDO, ES klienta, HTTP. Application nepoužívá `Request` ani `$_GET`. Presentation neobsahuje SQL a byznys logiku.
2. **Porty a adaptéry:** Každý vnější aspekt (MySQL, ES, Redis, souborový systém) je skryt za rozhraním v Domain. Přepínání implementací probíhá přes env proměnné a továrny bez změny Application/Domain.
3. **Serializace:** Používá se `symfony/serializer` s vlastními `PriceNormalizer` a `ProductIdNormalizer`. Ruční `toArray()` a `json_encode` chybějí v Application/Domain.
4. **Bezpečnost:** Všechny SQL dotazy používají prepared statements. CSRF tokeny na formulářích dashboardu. Validace toggle hodnot podle bílé listiny.
5. **Testovací pyramida:** Plné pokrytí Unit (8) → Integration (14) → Concurrent (2) → Functional (2). Bootstrap čistí storage před spuštěním.
6. **Kvalita kódu:** PHPStan level 6 + strict rules, php-cs-fixer s PSR-12/Symfony, `declare(strict_types=1)` ve všech souborech, absence `empty()`, `isset()`, `is_null()`, `==`.
7. **Frontend bez Node.js:** AssetMapper + Tailwind CSS binary + Alpine.js + Turbo poskytují moderní UX bez npm/webpack.
8. **Fantomové komponenty, chybějící v kódu:** `QueueInterface` (Domain port), `app:worker:start` (CLI příkaz), `tailwind.config.js` (konfigurační soubor). Tato dokumentace tyto entity neobsahuje.
9. **Optimistický counter display:** Důležitý UI detail – při async režimu `ProductController` zobrazuje `$actualCount + 1`, protože async increment ještě není zpracován workerem, ale uživatel očekává vidět aktuální hodnotu.
10. **Graceful degradation:** `RedisHealthAdapter` má nullable `\Redis` v konstruktoru, což umožňuje health-check pracovat i při nedostupném Redis připojení.
