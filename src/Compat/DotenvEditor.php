<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Compat;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\EnvKit;

/**
 * A drop-in `jackiedo/dotenv-editor`-style facade — resolves the SAME bound
 * EnvKit instance as the `EnvKit` facade, exposing the jackiedo-named aliases
 * (`getValue`/`setKey`/`setKeys`/`deleteKey`/`deleteKeys`/`keyExists`/`getKeys`/
 * `getEntries`/`getContent`) so existing call sites migrate with a class swap.
 *
 * @method static mixed getValue(string $key, mixed $default = null)
 * @method static EnvKit setKey(string $key, string $value)
 * @method static EnvKit setKeys(array<string, string> $pairs)
 * @method static EnvKit deleteKey(string $key)
 * @method static EnvKit deleteKeys(list<string> $keys)
 * @method static bool keyExists(string $key)
 * @method static list<string> getKeys()
 * @method static string getContent()
 *
 * @see EnvKit
 */
final class DotenvEditor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EnvKitInterface::class;
    }
}
