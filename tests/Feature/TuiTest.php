<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('quits immediately from the editor', function () {
    $this->bindEnv("A=1\n");

    $this->artisan('env:edit')
        ->expectsQuestion('Choose a key or action', 'Quit')
        ->assertExitCode(0);
});

it('edits a value through the interactive editor', function () {
    $this->bindEnv("APP_NAME=Acme\n", ['env-kit.auto_backup' => false]);

    $this->artisan('env:edit')
        ->expectsQuestion('Choose a key or action', 'APP_NAME')
        ->expectsQuestion('Edit [APP_NAME]', 'Edit value')
        ->expectsQuestion('New value for [APP_NAME]', 'NewName')
        ->expectsQuestion('Choose a key or action', 'Quit')
        ->assertExitCode(0);

    expect(EnvKit::get('APP_NAME'))->toBe('NewName');
});

it('adds a new key through the editor', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $this->artisan('env:edit')
        ->expectsQuestion('Choose a key or action', '＋ Add a new key')
        ->expectsQuestion('New key name', 'B')
        ->expectsQuestion('Value for B', '2')
        ->expectsQuestion('Choose a key or action', 'Quit')
        ->assertExitCode(0);

    expect(EnvKit::get('B'))->toBe('2');
});

it('deletes a key after confirmation', function () {
    $this->bindEnv("A=1\nB=2\n", ['env-kit.auto_backup' => false]);

    $this->artisan('env:edit')
        ->expectsQuestion('Choose a key or action', 'B')
        ->expectsQuestion('Edit [B]', 'Delete')
        ->expectsConfirmation('Delete [B]?', 'yes')
        ->expectsQuestion('Choose a key or action', 'Quit')
        ->assertExitCode(0);

    expect(EnvKit::has('B'))->toBeFalse();
});

it('surfaces an engine error without crashing the loop', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $this->artisan('env:edit')
        ->expectsQuestion('Choose a key or action', '＋ Add a new key')
        ->expectsQuestion('New key name', '1bad') // invalid → engine rejects
        ->expectsQuestion('Value for 1bad', 'x')
        ->expectsQuestion('Choose a key or action', 'Quit')
        ->assertExitCode(0);

    expect(EnvKit::has('1bad'))->toBeFalse();
});
