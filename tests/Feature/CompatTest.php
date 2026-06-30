<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Compat\DotenvEditor;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('addComment and addEmptyLine append to the file', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::addComment('Database settings')->addEmptyLine();

    expect((string) file_get_contents($path))->toContain('# Database settings');
});

it('the jackiedo aliases map to the idiomatic API', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::setKey('B', '2')->setKeys(['C' => '3', 'D' => '4']);

    expect(EnvKit::getValue('B'))->toBe('2')
        ->and(EnvKit::keyExists('C'))->toBeTrue()
        ->and(EnvKit::getKeys())->toContain('A', 'B', 'C', 'D')
        ->and(EnvKit::getContent())->toContain('B=2')
        ->and(EnvKit::getEntries())->toHaveCount(4);

    EnvKit::deleteKey('B')->deleteKeys(['C', 'D']);

    expect(EnvKit::keyExists('B'))->toBeFalse()
        ->and(EnvKit::has('C'))->toBeFalse();
});

it('the DotenvEditor compat facade drives the same bound engine', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    DotenvEditor::setKey('X', '9');

    expect(DotenvEditor::getValue('X'))->toBe('9')
        ->and(EnvKit::get('X'))->toBe('9'); // same scoped instance
});

it('the fake mirrors the compat aliases', function () {
    $fake = EnvKit::fake(['A' => '1']);

    $fake->setKey('B', '2')->setKeys(['C' => '3'])->addComment('x')->addEmptyLine();

    expect($fake->getValue('B'))->toBe('2')
        ->and($fake->keyExists('C'))->toBeTrue()
        ->and($fake->getKeys())->toContain('A', 'B', 'C')
        ->and($fake->getContent())->toContain('B=2');

    $fake->deleteKey('B')->deleteKeys(['C']);
    expect($fake->has('B'))->toBeFalse();
});
