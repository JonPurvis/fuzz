# Fuzz

Coverage-guided fuzz testing for [PestPHP](https://pestphp.com), powered by [nikic/php-fuzzer](https://github.com/nikic/PHP-Fuzzer).

Fuzzing searches for inputs that crash your code (or break invariants). It complements Pest [datasets](https://pestphp.com/docs/datasets): datasets confirm cases you already know; fuzz hunts for the ones you forgot.

## Requirements

- PHP 8.4+
- Pest 5

## Installation

```bash
composer require jonpurvis/fuzz --dev
```

## In your application

Say your app handles Stripe-style webhooks and trusts the decoded JSON a little too much:

```php
namespace App\Webhooks;

final class PayloadParser
{
    public static function eventName(string $json): string
    {
        $data = json_decode($json, true);

        // Assumes an object with a string "event" — fine for happy paths…
        return $data['event'];
    }
}
```

That helper is open to hostile shapes you probably never typed by hand: `null`, `[]`, `{}`, `{"type":"ping"}`, truncated JSON, nested junk, and whatever else arrives on the wire.

You *could* cover it with a Pest dataset — a predetermined list of values you already thought of:

```php
it('parses known webhook payloads', function (string $json, string $expected): void {
    expect(PayloadParser::eventName($json))->toBe($expected);
})->with([
    'checkout' => ['{"event":"checkout.session.completed"}', 'checkout.session.completed'],
    'invoice' => ['{"event":"invoice.paid"}', 'invoice.paid'],
]);

it('rejects known bad webhook payloads', function (string $json): void {
    expect(fn () => PayloadParser::eventName($json))->toThrow(TypeError::class);
})->with([
    'empty object' => ['{}'],
    'null json' => ['null'],
]);
```

Datasets are great for **regressions** and examples you care about by name. They only ever send what you listed.

Or you *could* use fuzz testing — because the input space is huge, attackers (and bugs) invent cases you did not list, and coverage feedback keeps mutating around your seeds until something crashes:

```php
use function Fuzz\fuzz;

it('webhook parser never fatals on hostile JSON', function (): void {
    fuzz(Closure::fromCallable([PayloadParser::class, 'eventName']))
        ->seed([
            '{"event":"checkout.session.completed"}',
            '{"event":"invoice.paid","id":"in_123"}',
        ])
        ->withDictionary(['{', '}', '[', ']', 'null', 'event', ':', ','])
        ->runs(2000)
        ->maxLen(64)
        ->saveCrashes()
        ->run();
});
```

Unlike a dataset, this does **not** check a fixed list of JSON strings. It **searches**:

1. Starts from `seed(...)` — your known-good examples become the initial library.
2. Mutates those bytes repeatedly (flip/delete/insert, splice in dictionary tokens).
3. Keeps inputs that hit **new code paths** (coverage-guided), then mutates those further.
4. Fails the Pest test if the target throws an uncaught `Error` / `TypeError` / times out — and, with `saveCrashes()` (default), writes the payload to `.pest/fuzz-crashes/{hash}/crash-*.txt`.

So a dataset answers “do these cases I thought of behave?” This fuzz test answers “can we find a case I did not list that still breaks `eventName`?”

Starting from a seed like `{"event":"invoice.paid","id":"in_123"}`, mutations might wander into inputs such as:

- `{}` / `[]` / `null` — valid JSON, wrong shape (no string `event`)
- `{"type":"invoice.paid"}` — object, but the key you assumed is missing
- `{"event":null}` / `{"event":[]}` — key present, value not a string
- `{"event":"invoice.paid"` — truncated / unbalanced braces
- `{event:"invoice.paid"}` — almost-JSON after dictionary splice
- `{"event":"invoice.paid","event":1}` — duplicate keys, odd types
- binary junk under 64 bytes that still reaches `json_decode`

You would rarely hand-author all of those into a dataset. The fuzzer is there to stumble into them (and similar) within the `runs` budget.

What the chain is doing:

- **`seed([...])`** — starting examples. Without seeds the fuzzer begins from empty/random input and spends longer reaching interesting JSON.
- **`withDictionary([...])`** — fragments the mutator is allowed to insert (here: JSON punctuation and `null` / `event`). That biases mutations toward structurally relevant junk instead of pure noise. You can also pass a path to a `.dict` file.
- **`runs(2000)`** — budget: at most 2000 target executions this test. Higher = more searching, slower CI. Keep this small in the default suite; raise it for overnight/soak runs.
- **`maxLen(64)`** — hard cap on input size in bytes. Stops the fuzzer from growing huge payloads when a small crash is enough.
- **`saveCrashes()`** — when a crash is found, persist the payload as `.pest/fuzz-crashes/{hash}/crash-*.txt` (override with `crashDir()`). Saving does not suppress the failure — Pest still fails so you can replay the input or promote it into a dataset.

Use **both**: datasets lock in known good/bad cases; fuzz hunts for the ones you forgot. When fuzz finds a crash, paste that payload into a named dataset so it never slips back in.

## Basic usage

Prefer **static** callables (or `Closure::fromCallable`) so the isolated worker does not need Pest's generated test class:

```php
use function Fuzz\fuzz;

it('headKey never fatals on hostile JSON', function () {
    fuzz(Closure::fromCallable([App\Support\HeadKey::class, 'parse']))
        ->seed(['{"key":"a"}'])
        ->withDictionary(['{', '}', 'null', 'key', ':', ','])
        ->runs(2000)
        ->maxLen(64)
        ->saveCrashes()
        ->run();
});
```

On crash, Pest fails the test. With `saveCrashes()` (default `true`), the exact payload is written to `.pest/fuzz-crashes/{hash}/crash-*.txt` (or your `crashDir()`), and the failure message may include `Crash saved: …`.

## Fluent API

| Method | Meaning |
|--|--|
| `runs(int)` | Max target executions (default 1000) |
| `maxLen(int)` | Max input byte length |
| `timeout(int)` | Per-input seconds (`pcntl`) |
| `withDictionary(array)` | `.dict` paths and/or keyword strings |
| `seed(array)` | Starting example inputs (strings or files) |
| `libraryDir(string)` | Where interesting inputs are kept (default `.pest/fuzz-library/{hash}`) |
| `crashDir(string)` | Where crashes are saved (default `.pest/fuzz-crashes/{hash}`) |
| `saveCrashes(bool)` | Persist crashing inputs to `crashDir` as `crash-*.txt` (default `true`; does not suppress the failure) |
| `allow(array)` | Domain exception classes to ignore |
| `run()` | Execute in an isolated worker |

### Terminology

- **Seed** — starting example you provide
- **Library** — growing set of interesting inputs (kept because they found new code paths)
- **Dictionary** — fragments the mutator may insert (`null`, `{`, `<script>`, …)
- **Crash** — uncaught `Error` / timeout / failed Pest expectation inside the target

## Development

```bash
composer install
composer test   # rector + pint + phpstan + pest
```

### Local PoC consumer

```bash
cd examples/poc
composer install
./vendor/bin/pest
```

That path-requires this package (same idea as `composer require` in a Laravel app) and shows datasets staying green while fuzz finds the `HeadKey` crash.

## Architecture note

`nikic/php-fuzzer` instruments PHP via include interception and overrides error/signal handlers. This plugin always runs the fuzzer in a **subprocess** (`bin/fuzz-worker.php`) so the parent Pest process stays clean.

## License

MIT
