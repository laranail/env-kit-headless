<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Authorization\DefaultUpdateGate;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteContext;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteDecision;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\InvalidEnvironmentException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\InvalidValueException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\NotEditableException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProductionGuardException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProtectedKeyException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\UnauthorizedUpdateException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\WriteVetoedException;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;

it('builds allow/deny decisions', function () {
    expect(WriteDecision::allow()->allowed)->toBeTrue()
        ->and(WriteDecision::allow()->reason)->toBeNull()
        ->and(WriteDecision::deny('nope')->allowed)->toBeFalse()
        ->and(WriteDecision::deny('nope')->reason)->toBe('nope');
});

it('exposes the commit through a WriteContext', function () {
    $original = EnvDocument::parse("A=1\n");
    $document = $original->withValue('A', '2')->withValue('NEW', 'x');
    $context = new CommitContext('/srv/.env', $document, $original, allowProduction: true, actor: 'bob', operation: 'restore');

    $write = new WriteContext($context, isProduction: true);

    expect($write->path())->toBe('/srv/.env')
        ->and($write->actor())->toBe('bob')
        ->and($write->operation())->toBe('restore')
        ->and($write->allowProduction())->toBeTrue()
        ->and($write->isProduction)->toBeTrue()
        ->and($write->changedKeys())->toEqualCanonicalizing(['A', 'NEW'])
        ->and($write->change('A'))->toBe(['old' => '1', 'new' => '2'])
        ->and($write->change('NEW'))->toBe(['old' => null, 'new' => 'x']);
});

it('the default gate always allows (production stays ProductionGuard\'s job)', function () {
    $context = new WriteContext(new CommitContext('/x', EnvDocument::parse(''), EnvDocument::parse('')), isProduction: true);

    expect((new DefaultUpdateGate)->inspect($context)->allowed)->toBeTrue();
});

it('maps refusal exceptions to stable reason codes', function () {
    expect(ProductionGuardException::make()->envKitReason())->toBe('production')
        ->and(ProtectedKeyException::for('K')->envKitReason())->toBe('protected')
        ->and(NotEditableException::for('K')->envKitReason())->toBe('not_editable')
        ->and(InvalidValueException::nulByte('K')->envKitReason())->toBe('invalid')
        ->and(UnauthorizedUpdateException::because('x')->envKitReason())->toBe('unauthorized')
        ->and(WriteVetoedException::because('x')->envKitReason())->toBe('vetoed');
});

it('builds precise value/environment exception messages', function () {
    expect(InvalidValueException::nulByte('SECRET')->getMessage())->toBe('Value contains a NUL byte (key SECRET).')
        ->and(InvalidValueException::nulByte()->getMessage())->toBe('Value contains a NUL byte.')
        ->and(InvalidValueException::tooLong('BIG', 10)->getMessage())->toBe('Value exceeds the maximum length of 10 bytes (key BIG).')
        ->and(InvalidValueException::tooLong(null, 5)->getMessage())->toBe('Value exceeds the maximum length of 5 bytes.')
        ->and(InvalidEnvironmentException::for('../x')->getMessage())->toContain('../x');
});
