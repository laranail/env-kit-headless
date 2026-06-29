<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\EnvKit\Headless\Audit\AuditEvent;
use Simtabi\Laranail\EnvKit\Headless\Audit\FileAuditSink;
use Simtabi\Laranail\EnvKit\Headless\Contracts\AuditSinkInterface;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Events\AfterWrite;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Audit;
use Simtabi\Laranail\EnvKit\Headless\Security\SecretRedactor;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('writes a redacted JSON-lines audit record on commit', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.audit.enabled' => true]);
    $auditPath = dirname($path).'/audit.log';

    EnvKit::set('DB_PASSWORD', 'topsecret123');

    expect(is_file($auditPath))->toBeTrue();

    $lines = file($auditPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $last = (string) end($lines);
    $record = json_decode($last, true);

    expect($last)->not->toContain('topsecret123') // the raw secret never reaches the log
        ->and($record['changes'][0]['key'])->toBe('DB_PASSWORD')
        ->and($record['changes'][0]['new'])->not->toBe('topsecret123');
});

it('dispatches an AfterWrite event carrying redacted changes', function () {
    Event::fake();
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::set('API_TOKEN', 'abc123');

    Event::assertDispatched(AfterWrite::class, function (AfterWrite $event): bool {
        return $event->changes[0]['key'] === 'API_TOKEN'
            && $event->changes[0]['new'] !== 'abc123'; // masked (API_TOKEN matches *_TOKEN)
    });
});

it('fans out to a sink registered via configure()', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $spy = new class implements AuditSinkInterface
    {
        public int $records = 0;

        public function record(AuditEvent $event): void
        {
            $this->records++;
        }
    };

    EnvKit::configure()->registerAuditSink($spy);
    EnvKit::set('B', '2');

    expect($spy->records)->toBe(1);
});

it('does not audit a no-op (unchanged) write', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.audit.enabled' => true]);
    $auditPath = dirname($path).'/audit.log';

    EnvKit::set('A', '1'); // same value → no-op, no commit, no audit

    expect(is_file($auditPath))->toBeFalse();
});

it('keeps the FileAuditSink output parseable as JSON lines', function () {
    $path = $this->bindEnv("A=1\n");
    $auditPath = dirname($path).'/audit.log';

    $sink = new FileAuditSink($auditPath);
    $sink->record(new AuditEvent($path, [['key' => 'X', 'old' => null, 'new' => '1']], 'tester', 1700000000));
    $sink->record(new AuditEvent($path, [['key' => 'Y', 'old' => '1', 'new' => '2']], null, 1700000001));

    $lines = file($auditPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    expect($lines)->toHaveCount(2)
        ->and(json_decode($lines[0], true)['actor'])->toBe('tester')
        ->and(json_decode($lines[1], true)['changes'][0]['key'])->toBe('Y');
});

it('creates the audit directory and writes a newline-terminated, slash-unescaped JSON line', function () {
    $base = sys_get_temp_dir().'/envkit-sink-'.bin2hex(random_bytes(5));
    $auditPath = $base.'/nested/dir/audit.log'; // the parent directory does not exist yet

    expect(is_dir(dirname($auditPath)))->toBeFalse();

    $sink = new FileAuditSink($auditPath);
    $oldUmask = umask(0); // so mkdir applies the exact 0755 mode rather than a umask-masked one
    try {
        $sink->record(new AuditEvent($auditPath, [['key' => 'X', 'old' => null, 'new' => '1']], 'tester', 1700000000));
    } finally {
        umask($oldUmask);
    }

    // The missing directory was created (so the write succeeded) with mode 0755.
    expect(is_file($auditPath))->toBeTrue()
        ->and(fileperms(dirname($auditPath)) & 0777)->toBe(0755);

    $raw = (string) file_get_contents($auditPath);

    expect(str_starts_with($raw, '{'))->toBeTrue()        // payload first, not the EOL
        ->and(str_ends_with($raw, \PHP_EOL))->toBeTrue()  // line . PHP_EOL, not PHP_EOL . line
        ->and($raw)->toContain($auditPath);               // slashes left unescaped (UNESCAPED_SLASHES on)

    $decoded = json_decode(trim($raw), true);
    expect($decoded['path'])->toBe($auditPath)
        ->and($decoded['actor'])->toBe('tester');
});

it('records redacted old and new values to every sink and returns the piped result', function () {
    $original = EnvDocument::parse("MOD=oldval\nDB_PASSWORD=oldsecret\n");
    $document = $original->withValue('MOD', 'newval')->withValue('DB_PASSWORD', 'newsecret');
    $context = new CommitContext('/srv/app/.env', $document, $original);

    $sink = new class implements AuditSinkInterface
    {
        public ?AuditEvent $event = null;

        public function record(AuditEvent $event): void
        {
            $this->event = $event;
        }
    };

    // No dispatcher passed: the null-safe dispatch must be skipped, not blow up.
    $pipe = new Audit([$sink], new SecretRedactor, null, 'tester');

    $result = $pipe->handle($context, fn (CommitContext $c): string => 'piped');

    expect($result)->toBe('piped')
        ->and($sink->event)->toBeInstanceOf(AuditEvent::class)
        ->and($sink->event->actor)->toBe('tester')
        ->and($sink->event->path)->toBe('/srv/app/.env');

    $byKey = [];
    foreach ($sink->event->changes as $change) {
        $byKey[$change['key']] = $change;
    }

    // Non-secret key: real old/new values flow through verbatim.
    expect($byKey['MOD']['old'])->toBe('oldval')
        ->and($byKey['MOD']['new'])->toBe('newval')
        // Secret key: both old and new are masked, never the raw value.
        ->and($byKey['DB_PASSWORD']['old'])->not->toBe('oldsecret')
        ->and($byKey['DB_PASSWORD']['new'])->not->toBe('newsecret');
});
