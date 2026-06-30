# Programmatic API

Edit `.env` from a controller, job, or service. Three entry points resolve the
same engine:

```php
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;            // facade
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface; // DI
env_kit('APP_NAME');                                            // helper
```

```php
public function __construct(private readonly EnvKitInterface $env) {}
```

## Reads

```php
EnvKit::get('APP_NAME', 'default');     // raw string (or default)
EnvKit::has('MAIL_HOST');               // bool
EnvKit::missing('NOPE');                // bool
EnvKit::all();                          // array<string, string>
EnvKit::keys();                         // list<string>
EnvKit::only(['A', 'B']);               // subset
EnvKit::except(['SECRET']);             // complement
EnvKit::group('MAIL');                  // every MAIL_* key
EnvKit::interpolated('DB_DSN');         // ${VAR} resolved
EnvKit::raw();                          // the file as a string
EnvKit::entries();                      // Collection of Setter metadata
EnvKit::entry('APP_NAME');              // ?Setter — one key's metadata (or null)
```

### Typed getters

```php
EnvKit::getString('APP_NAME');          // ?string
EnvKit::getBool('APP_DEBUG', false);    // true/1/yes/on → true
EnvKit::getInt('PORT', 8080);           // ?int
EnvKit::getFloat('RATE');               // ?float
EnvKit::getArray('HOSTS');              // JSON array or comma list → array
EnvKit::getJson('FLAGS');               // decoded JSON
```

## Writes

```php
EnvKit::set('MAIL_HOST', 'smtp.acme.test');
EnvKit::set('PATH_BIN', '/usr/bin', ['export' => true]);  // export PATH_BIN=...
EnvKit::forget('OLD_KEY');
EnvKit::rename('OLD', 'NEW');
EnvKit::setMany(['A' => '1', 'B' => '2']);
```

### Intent-revealing writes

```php
EnvKit::update('MAIL_HOST', 'smtp.new.test');   // set an EXISTING key (KeyNotFoundException if absent)
EnvKit::setOrUpdate('MAIL_HOST', 'smtp.test');  // explicit upsert (semantic alias of set())
EnvKit::setIfMissing('MAIL_PORT', '587');       // set only when absent (else a no-op)
EnvKit::forgetMany(['OLD_A', 'OLD_B']);         // remove several keys in one commit
EnvKit::setExport('PATH_BIN', true);            // set/clear `export ` on an existing key (KeyNotFoundException if absent)
```

## Three persistence modes

Gated by `config('env-kit.auto_commit')`:

**Immediate** (default) — each call is its own atomic commit:

```php
EnvKit::set('A', '1');   // committed now
```

**Transaction** — batch many mutations into one commit:

```php
EnvKit::transaction(function ($session) {
    $session->set('A', '1')->set('B', '2')->forget('C');
}); // one atomic write
```

**Staged session** — drive and save manually:

```php
$session = EnvKit::open();
$session->set('A', '1');
if ($session->isDirty()) {
    $session->preview();   // diff without writing
    $session->save();      // commit (or ->discard())
}
```

## Guards, backups, encryption

```php
EnvKit::allowProduction()->set('MAINTENANCE', 'true'); // opt past the prod guard
$backup = EnvKit::backup();                            // snapshot
$backup = EnvKit::backup('before-deploy');             // labelled snapshot (label folded into the filename)
EnvKit::restore($backup->name);                        // roll back

EnvKit::backups()->delete($backup->name);              // remove one backup → bool
EnvKit::backups()->deleteOlderThan(30);                // prune backups older than N days → int removed

EnvKit::setEncrypted('STRIPE_SECRET', $plaintext);     // stored encrypted
EnvKit::getDecrypted('STRIPE_SECRET');                 // → plaintext
```

## Schema validation

Validate the `.env` against a schema (config-seeded rules merged with any chained
at runtime). See **[Schema](schema.md)** for the full rule set.

```php
EnvKit::schema()->required('APP_KEY')->in('APP_ENV', ['local', 'production']);
$result = EnvKit::validate();   // ValidationResult: passed() / failed() / errors() / messages()
EnvKit::isValid();              // bool
EnvKit::assertValid();          // throws SchemaException on failure
```

## `.env.example` sync

Keep a live `.env` aligned with its `.env.example` template:

```php
EnvKit::examplePath();              // the sibling .env.example path
EnvKit::missingFromExample();      // list<string> — keys in the example, absent here
EnvKit::syncFromExample();         // add every missing key (with the example's value)
EnvKit::missingFromExample('/path/to/.env.dist');   // point at a custom template
```

## Generating secrets

```php
$token  = EnvKit::generate();                       // 'token' — random hex token
$hex    = EnvKit::generate('hex', ['bytes' => 64]); // wider entropy
$b64    = EnvKit::generate('base64');               // url-safe base64 token
$appKey = EnvKit::generate('app_key');              // Laravel base64:… APP_KEY
EnvKit::set('JWT_SECRET', EnvKit::generate());      // generate() returns the value; set it explicitly so the guards apply
```

## Diagnostics & transfer

```php
EnvKit::inspect();                 // list<Diagnostic> (doctor rules)
EnvKit::diff(base_path('.env.example'));   // only_here / only_there / changed
EnvKit::export('json');            // serialize
EnvKit::import($json, 'json');     // apply (through the commit pipeline)
```

## Targeting another file

```php
EnvKit::file(base_path('.env.staging'))->set('APP_ENV', 'staging');
EnvKit::on('testing')->get('DB_DATABASE');  // .env.testing
```

## Testing

```php
$fake = EnvKit::fake(['APP_NAME' => 'Acme']);   // in-memory, no disk
$service->run();
$fake->assertSet('NEW_KEY');
$fake->assertForgotten('OLD_KEY');
```

---

[← Docs index](../../README.md#documentation)
