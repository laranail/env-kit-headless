<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline;

use Illuminate\Pipeline\Pipeline;
use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Audit;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Backup;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Guard;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\ValidateKeys;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Verify;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Write;
use Simtabi\Laranail\EnvKit\Headless\Security\EditableKeys;
use Simtabi\Laranail\EnvKit\Headless\Security\KeyValidator;
use Simtabi\Laranail\EnvKit\Headless\Security\ProductionGuard;
use Simtabi\Laranail\EnvKit\Headless\Security\ProtectedKeys;
use Simtabi\Laranail\EnvKit\Headless\Writer\AtomicEnvWriter;
use Simtabi\Laranail\EnvKit\Headless\Writer\IntegrityVerifier;

/**
 * Runs a commit through `validate → guard → [consumer middleware] → backup →
 * write → verify`. Consumer middleware (pushed via {@see push()}) runs after the
 * built-in guards but before the durable write — and a pipe that throws aborts
 * the commit before anything is written. Encryption/audit pipes slot into the
 * middleware band in later slices.
 */
final class CommitPipeline
{
    /** @var list<object> */
    private array $middleware = [];

    public function __construct(
        private readonly ValidateKeys $validate,
        private readonly Guard $guard,
        private readonly Backup $backup,
        private readonly Write $write,
        private readonly Verify $verify,
        private readonly ?Audit $audit = null,
    ) {}

    public static function default(
        ?WriterInterface $writer = null,
        ?BackupManager $backups = null,
        ?ProductionGuard $production = null,
        ?ProtectedKeys $protected = null,
        ?KeyValidator $keys = null,
        ?Audit $audit = null,
        ?EditableKeys $editable = null,
    ): self {
        $writer ??= new AtomicEnvWriter;

        return new self(
            validate: new ValidateKeys($keys ?? new KeyValidator),
            guard: new Guard(
                $production ?? new ProductionGuard(false),
                $protected ?? new ProtectedKeys([]),
                $editable ?? new EditableKeys,
            ),
            backup: new Backup($backups),
            write: new Write($writer),
            verify: new Verify($writer, new IntegrityVerifier),
            audit: $audit,
        );
    }

    /** Append a consumer middleware pipe (runs before the write). */
    public function push(object $pipe): self
    {
        $this->middleware[] = $pipe;

        return $this;
    }

    public function run(CommitContext $context): void
    {
        $pipes = [$this->validate, $this->guard, ...$this->middleware, $this->backup, $this->write, $this->verify];

        if ($this->audit !== null) {
            $pipes[] = $this->audit;
        }

        (new Pipeline)
            ->send($context)
            ->through($pipes)
            ->thenReturn();
    }
}
