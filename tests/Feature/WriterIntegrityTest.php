<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\FileNotWritableException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\IntegrityException;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Verify;
use Simtabi\Laranail\EnvKit\Headless\Writer\AtomicEnvWriter;
use Simtabi\Laranail\EnvKit\Headless\Writer\IntegrityVerifier;

function envkitTmp(): string
{
    return sys_get_temp_dir().'/envkit-wi-'.bin2hex(random_bytes(5)).'.env';
}

it('verifies a written file against the expected document', function () {
    $path = envkitTmp();
    file_put_contents($path, "A=1\nB=2\n");
    $verifier = new IntegrityVerifier;

    expect($verifier->verify($path, EnvDocument::parse("A=1\nB=2\n")))->toBeTrue()       // match
        ->and($verifier->verify($path, EnvDocument::parse("A=9\n")))->toBeFalse()        // value mismatch
        ->and($verifier->verify('/no/such/envkit/file', EnvDocument::parse('')))->toBeFalse(); // missing file

    @unlink($path);
});

it('rolls back a NEW file by deleting it when verification fails (no previous bytes)', function () {
    $path = envkitTmp();
    $badWriter = new class implements WriterInterface
    {
        public function write(string $path, string $contents): void
        {
            file_put_contents($path, "WRONG=1\n"); // diverges from the document
        }
    };

    $badWriter->write($path, "A=1\n");                 // the (bad) durable write happened
    $context = new CommitContext($path, EnvDocument::parse("A=1\n"), EnvDocument::parse(''));
    // $context->previous stays null → a fresh file, so rollback must unlink it.

    $verify = new Verify($badWriter, new IntegrityVerifier);

    expect(fn () => $verify->handle($context, fn (): null => null))->toThrow(IntegrityException::class)
        ->and(is_file($path))->toBeFalse(); // deleted on rollback
});

it('rolls back to the previous bytes when an existing file fails verification', function () {
    $path = envkitTmp();
    file_put_contents($path, "WRONG=1\n");
    $writer = new AtomicEnvWriter;

    $context = new CommitContext($path, EnvDocument::parse("A=1\n"), EnvDocument::parse("A=0\n"));
    $context->previous = "A=0\n"; // the captured pre-write bytes

    expect(fn () => (new Verify($writer, new IntegrityVerifier))->handle($context, fn (): null => null))
        ->toThrow(IntegrityException::class);

    expect((string) file_get_contents($path))->toBe("A=0\n"); // restored
    @unlink($path);
});

it('refuses to write into a non-existent directory', function () {
    expect(fn () => (new AtomicEnvWriter)->write('/no/such/envkit/dir/.env', "A=1\n"))
        ->toThrow(FileNotWritableException::class);
});

it('refuses to write into a read-only directory', function () {
    $dir = sys_get_temp_dir().'/envkit-ro-'.bin2hex(random_bytes(5));
    mkdir($dir, 0500);

    try {
        expect(fn () => (new AtomicEnvWriter)->write($dir.'/.env', "A=1\n"))
            ->toThrow(FileNotWritableException::class);
    } finally {
        chmod($dir, 0700);
        rmdir($dir);
    }
})->skip(fn () => is_writable('/'), 'running as root — directory perms are bypassed');
