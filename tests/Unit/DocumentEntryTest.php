<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Doctor\Rules\ByteOrderMark;
use Simtabi\Laranail\EnvKit\Headless\Document\Entry\Comment;
use Simtabi\Laranail\EnvKit\Headless\Document\Entry\Setter;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;

it('the ByteOrderMark rule flags a UTF-8 BOM and passes a clean file', function () {
    $withBom = EnvDocument::parse("\u{FEFF}A=1\n");
    $clean = EnvDocument::parse("A=1\n");

    $diagnostics = (new ByteOrderMark)->check($withBom);

    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]->severity)->toBe('warning')
        ->and((new ByteOrderMark)->check($clean))->toBe([]);
});

it('renders a parsed comment verbatim and reconstructs a code-created one', function () {
    // A parsed comment keeps its exact original spacing (format-preserving round-trip).
    expect(EnvDocument::parse("#   spaced original comment\nA=1\n")->render())
        ->toContain('#   spaced original comment');

    expect((new Comment('hello'))->render())->toBe('# hello')
        ->and((new Comment(''))->render())->toBe('#');
});

it('Setter::withValue makes a dirty copy and isDirty reflects whether it was parsed', function () {
    $parsed = EnvDocument::parse("A=1\n")->setters()[0];
    expect($parsed->isDirty())->toBeFalse(); // parsed → carries its original line

    $coded = new Setter('A', '1');
    expect($coded->isDirty())->toBeTrue();    // code-created → no original

    $updated = $coded->withValue('2');
    expect($updated->key)->toBe('A')
        ->and($updated->value)->toBe('2')
        ->and($updated->isDirty())->toBeTrue();
});
