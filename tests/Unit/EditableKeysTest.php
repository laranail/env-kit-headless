<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Exceptions\NotEditableException;
use Simtabi\Laranail\EnvKit\Headless\Security\EditableKeys;

it('treats every key as editable when the allowlist is empty', function () {
    $editable = new EditableKeys;

    expect($editable->isEditable('ANYTHING'))->toBeTrue()
        ->and($editable->isEditable('DB_PASSWORD'))->toBeTrue();
});

it('allows only listed keys when the allowlist is non-empty', function () {
    $editable = new EditableKeys(['APP_*', 'MAIL_HOST']);

    expect($editable->isEditable('APP_NAME'))->toBeTrue()   // wildcard
        ->and($editable->isEditable('MAIL_HOST'))->toBeTrue() // exact
        ->and($editable->isEditable('DB_HOST'))->toBeFalse(); // not listed
});

it('matches patterns case-insensitively', function () {
    expect((new EditableKeys(['app_*']))->isEditable('APP_NAME'))->toBeTrue();
});

it('guard() passes an editable key and throws on a non-editable one', function () {
    $editable = new EditableKeys(['APP_*']);

    $editable->guard('APP_NAME'); // no throw

    expect(fn () => $editable->guard('SECRET_TOKEN'))
        ->toThrow(NotEditableException::class, 'SECRET_TOKEN');
});

it('guard() never throws when the allowlist is empty', function () {
    (new EditableKeys)->guard('WHATEVER');

    expect(true)->toBeTrue();
});
