# Import / Export

Move env data in and out of the file through the **Porter**. `json`, `csv` and
`dotenv` ship built-in; `yaml` registers automatically when `symfony/yaml` is
installed. The format set is extensible.

| Format | Built-in | Notes |
|--------|----------|-------|
| `json` | ✓ | Pretty-printed object. |
| `csv` | ✓ | `KEY,VALUE` with a header row (RFC-4180). |
| `dotenv` | ✓ | Plain `KEY=VALUE` lines — round-trips through another `.env`-shaped file. |
| `yaml` | only with `symfony/yaml` | Pull it in via the package's `suggest`: `composer require symfony/yaml`. |

## CLI

```bash
php artisan env:export                              # JSON to stdout
php artisan env:export --format=csv --output=env.csv
php artisan env:export --format=dotenv --output=.env.copy
php artisan env:export --format=yaml --output=env.yaml   # needs symfony/yaml
php artisan env:import env.json                     # JSON (default)
php artisan env:import env.csv --format=csv
php artisan env:import .env.copy --format=dotenv
```

Imports run through the full commit pipeline — so keys are validated, guards apply,
the write is atomic, and the change is audited.

## Programmatic

```php
$json   = EnvKit::export('json');     // pretty-printed object
$csv    = EnvKit::export('csv');      // KEY,VALUE with a header row (RFC-4180)
$dotenv = EnvKit::export('dotenv');   // plain KEY=VALUE lines
$yaml   = EnvKit::export('yaml');     // when symfony/yaml is installed

EnvKit::import($json, 'json');
EnvKit::import($csv, 'csv');
EnvKit::import($dotenv, 'dotenv');
```

## Custom formats

Implement `PortFormatInterface` (`name()`, `export()`, `import()`) and register it:

```php
use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;

final class YamlFormat implements PortFormatInterface
{
    public function name(): string { return 'yaml'; }

    public function export(array $values): string { /* … */ }

    public function import(string $content): array { /* … */ }
}

EnvKit::configure()->registerPortFormat(new YamlFormat);
// php artisan env:export --format=yaml
```

---

[← Docs index](../../README.md#documentation)
