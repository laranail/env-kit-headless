<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Audit\AuditEvent;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;

it('reports added, modified and removed keys via changedKeys()', function () {
    // KEEP is unchanged; MOD is modified; ADDED is new; GONE is removed.
    // Values deliberately differ from their key names so a key-vs-value mix-up is visible.
    $original = EnvDocument::parse("KEEP=keepval\nGONE=goneval\nMOD=oldval\n");
    $document = $original
        ->withValue('MOD', 'newval')
        ->withValue('ADDED', 'addedval')
        ->without('GONE');

    $context = new CommitContext('/srv/app/.env', $document, $original);

    // First the after-loop (MOD modified, ADDED new), then the before-loop (GONE removed).
    // KEEP is unchanged so it must never appear.
    expect($context->changedKeys())->toBe(['MOD', 'ADDED', 'GONE']);
});

it('treats an identical document as having no changed keys', function () {
    $original = EnvDocument::parse("A=1\nB=2\n");

    $context = new CommitContext('/srv/app/.env', $original, $original);

    expect($context->changedKeys())->toBe([]);
});

it('exposes path, document, original and a mutable previous; allowProduction defaults to false', function () {
    $original = EnvDocument::parse("A=1\n");
    $document = $original->withValue('A', '2');

    $context = new CommitContext('/etc/app/.env', $document, $original, true);

    expect($context->path)->toBe('/etc/app/.env')
        ->and($context->document)->toBe($document)
        ->and($context->original)->toBe($original)
        ->and($context->allowProduction)->toBeTrue()
        ->and($context->previous)->toBeNull();

    $context->previous = "A=1\n";
    expect($context->previous)->toBe("A=1\n");

    $default = new CommitContext('/etc/app/.env', $document, $original);
    expect($default->allowProduction)->toBeFalse();
});

it('exposes path, actor, occurred_at and changes through AuditEvent::toArray()', function () {
    $changes = [['key' => 'A', 'old' => null, 'new' => '1']];

    $event = new AuditEvent('/srv/app/.env', $changes, 'tester', 1700000000);

    expect($event->toArray())->toBe([
        'path' => '/srv/app/.env',
        'actor' => 'tester',
        'occurred_at' => 1700000000,
        'changes' => $changes,
    ]);
});

it('carries a null actor through AuditEvent::toArray()', function () {
    $event = new AuditEvent('/srv/app/.env', [], null, 1700000001);

    expect($event->toArray()['actor'])->toBeNull()
        ->and($event->toArray()['occurred_at'])->toBe(1700000001);
});
