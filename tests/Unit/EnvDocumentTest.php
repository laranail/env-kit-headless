<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Document\Entry\Setter;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;

it('exposes only setter entries from setters(), skipping comments and blanks', function () {
    $doc = EnvDocument::parse("# header\nA=1\n\nB=2\n");

    $setters = $doc->setters();

    expect($setters)->toHaveCount(2)
        ->and($setters[0])->toBeInstanceOf(Setter::class)
        ->and($setters[1])->toBeInstanceOf(Setter::class)
        ->and($setters[0]->key)->toBe('A')
        ->and($setters[1]->key)->toBe('B');
});

it('reads values and membership precisely', function () {
    $doc = EnvDocument::parse("A=1\nB=2\n");

    expect($doc->has('A'))->toBeTrue()
        ->and($doc->has('MISSING'))->toBeFalse()
        ->and($doc->get('A'))->toBe('1')
        ->and($doc->get('MISSING'))->toBeNull()
        ->and($doc->keys())->toBe(['A', 'B']);
});

it('reports last-wins for duplicate keys in toArray()', function () {
    $doc = EnvDocument::parse("A=1\nA=2\n");

    expect($doc->toArray())->toBe(['A' => '2']);
});

it('appends a brand-new key at the end', function () {
    $doc = EnvDocument::parse("A=1\n")->withValue('B', '2');

    expect($doc->render())->toBe("A=1\nB=2\n")
        ->and($doc->keys())->toBe(['A', 'B']);
});

it('updates an existing key in place, OR-combining the export flag', function () {
    // existing export is false; passing export=true must turn it on (|| not &&).
    $doc = EnvDocument::parse("A=1\n")->withValue('A', '2', true);

    expect($doc->render())->toBe("export A=2\n");
});

it('keeps an existing export flag when updating without re-exporting', function () {
    $doc = EnvDocument::parse("export A=1\n")->withValue('A', '2');

    expect($doc->render())->toBe("export A=2\n");
});

it('updates only the first of duplicate keys when setting a value', function () {
    $doc = EnvDocument::parse("A=1\nA=2\n")->withValue('A', '9');

    expect($doc->render())->toBe("A=9\nA=2\n");
});

it('removes only the targeted setter, preserving other lines', function () {
    $doc = EnvDocument::parse("# keep me\nA=1\nB=2\n")->without('A');

    expect($doc->render())->toBe("# keep me\nB=2\n")
        ->and($doc->has('A'))->toBeFalse()
        ->and($doc->has('B'))->toBeTrue()
        ->and($doc->keys())->toBe(['B']);
});

it('renames a key in place, preserving its position and value', function () {
    $doc = EnvDocument::parse("A=1\nB=2\n")->renamed('A', 'RENAMED');

    expect($doc->render())->toBe("RENAMED=1\nB=2\n")
        ->and($doc->keys())->toBe(['RENAMED', 'B'])
        ->and($doc->get('RENAMED'))->toBe('1');
});

it('renames only the first of duplicate keys', function () {
    $doc = EnvDocument::parse("A=1\nA=2\n")->renamed('A', 'B');

    expect($doc->render())->toBe("B=1\nA=2\n");
});

it('reproduces eol, BOM and trailing-newline metadata', function () {
    $doc = EnvDocument::parse("\xEF\xBB\xBF"."A=1\r\nB=2");

    expect($doc->eol())->toBe("\r\n")
        ->and($doc->hasBom())->toBeTrue()
        ->and($doc->hasTrailingNewline())->toBeFalse();
});
