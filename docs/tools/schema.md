# Schema validation

EnvKit can validate a `.env` against a declarative schema — useful in CI, a
deploy gate, or a FormRequest. Rules come from two sources that **merge**:
`config('env-kit.schema')` (provider-seeded) and anything you chain at runtime
through `EnvKit::schema()`.

> Type rules only fire on a **present, non-empty** value. Chain `required()` to
> also enforce that the key is set — otherwise a missing key passes every type
> rule.

## Building a schema

`EnvKit::schema()` returns a fluent `EnvSchema` builder:

```php
EnvKit::schema()
    ->required('APP_KEY')
    ->in('APP_ENV', ['local', 'testing', 'staging', 'production'])
    ->boolean('APP_DEBUG')
    ->url('APP_URL')
    ->integer('REDIS_PORT')
    ->email('MAIL_FROM_ADDRESS')
    ->regex('APP_NAME', '/^[\w -]+$/');
```

### Rules

| Rule | Passes when the value… |
|------|------------------------|
| `required($key)` | is present and non-empty |
| `string($key)` | always (every at-rest value is a string) |
| `integer($key)` | matches `-?\d+` |
| `boolean($key)` | is `true/false/1/0/yes/no/on/off` (any case) |
| `number($key)` | is numeric |
| `in($key, [...])` | is one of the listed values |
| `regex($key, $pattern)` | matches the pattern |
| `url($key)` | is a valid URL |
| `email($key)` | is a valid email |

### Laravel-style specs

`define()` accepts a pipe-delimited (or list) rule spec, like a Laravel validator:

```php
EnvKit::schema()->define('APP_ENV', 'required|in:local,production');
EnvKit::schema()->define('REDIS_PORT', ['required', 'integer']);
```

## Running it

```php
$result = EnvKit::validate();   // ValidationResult
$result->passed();              // bool
$result->failed();              // bool
$result->errors();              // array<string, list<string>> — key => failure messages
$result->messages();            // list<string> — flat "KEY message" lines

EnvKit::isValid();              // bool shortcut
EnvKit::assertValid();          // returns $this, or throws SchemaException
```

`assertValid()` throws a `SchemaException` carrying the flat message list — drop
it into a boot check or a deploy step to fail fast on an unsatisfied `.env`.

## Config-seeded rules

Declare a baseline schema in `config/env-kit.php` so it applies everywhere
(including `env:validate`) without code:

```php
// config/env-kit.php
'schema' => [
    'APP_ENV'   => 'required|in:local,testing,staging,production',
    'APP_DEBUG' => 'boolean',
    'APP_URL'   => 'url',
],
```

These seed the schema once; runtime `schema()->…` rules merge on top. See
**[Configuration](../configuration.md)** for the `schema` key.

## In a FormRequest

`MatchesEnvSchema` is a Laravel `ValidationRule` that applies one key's schema
rules — so a WebUI form validates a value exactly as the CLI and programmatic
API do:

```php
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Rules\MatchesEnvSchema;

public function rules(): array
{
    $schema = EnvKit::schema();

    return [
        'value' => ['required', new MatchesEnvSchema($schema, 'APP_ENV')],
    ];
}
```

## CLI

`php artisan env:validate` runs well-formedness checks **and** the configured
schema, exiting `3` on any failure — see [CLI](cli.md).

---

[← Docs index](../../README.md#documentation)
