<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static string|null getString(string $key, ?string $default = null)
 * @method static bool|null getBool(string $key, ?bool $default = null)
 * @method static int|null getInt(string $key, ?int $default = null)
 * @method static float|null getFloat(string $key, ?float $default = null)
 * @method static array<int|string, mixed>|null getArray(string $key, array<int|string, mixed>|null $default = null)
 * @method static mixed getJson(string $key, mixed $default = null)
 * @method static bool has(string $key)
 * @method static bool missing(string $key)
 * @method static array<string, string> all()
 * @method static list<string> keys()
 * @method static array<string, string> only(list<string> $keys)
 * @method static array<string, string> except(list<string> $keys)
 * @method static array<string, string> group(string $prefix)
 * @method static string raw()
 * @method static mixed interpolated(string $key, mixed $default = null)
 * @method static \Illuminate\Support\Collection<int, \Simtabi\Laranail\EnvKit\Headless\Document\Entry\Setter> entries()
 * @method static \Simtabi\Laranail\EnvKit\Headless\EnvKit set(string $key, string $value, array<string, mixed> $options = [])
 * @method static \Simtabi\Laranail\EnvKit\Headless\EnvKit forget(string $key)
 * @method static \Simtabi\Laranail\EnvKit\Headless\EnvKit rename(string $from, string $to)
 * @method static \Simtabi\Laranail\EnvKit\Headless\EnvKit setMany(array<string, string> $pairs)
 * @method static mixed transaction(\Closure $callback)
 * @method static \Simtabi\Laranail\EnvKit\Headless\Session\EditSession open()
 * @method static \Simtabi\Laranail\EnvKit\Headless\EnvKit save()
 * @method static \Simtabi\Laranail\EnvKit\Headless\EnvKit allowProduction()
 * @method static \Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager backups()
 * @method static \Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator configure()
 *
 * @see \Simtabi\Laranail\EnvKit\Headless\EnvKit
 */
final class EnvKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EnvKitInterface::class;
    }
}
