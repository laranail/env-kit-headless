<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Support;

/**
 * Compares a live `.env` against its `.env.example` template — the keys it is
 * missing, the keys it has beyond the template, and whether it is in sync.
 * Pure (operates on key→value maps); EnvKit does the file I/O.
 */
final class ExampleSync
{
    /**
     * Keys present in $example but absent from $env (what the .env still needs).
     *
     * @param  array<string, string>  $env
     * @param  array<string, string>  $example
     * @return list<string>
     */
    public function missing(array $env, array $example): array
    {
        return array_values(array_diff(array_keys($example), array_keys($env)));
    }

    /**
     * Keys present in $env but absent from $example (undocumented extras).
     *
     * @param  array<string, string>  $env
     * @param  array<string, string>  $example
     * @return list<string>
     */
    public function extra(array $env, array $example): array
    {
        return array_values(array_diff(array_keys($env), array_keys($example)));
    }

    /**
     * @param  array<string, string>  $env
     * @param  array<string, string>  $example
     */
    public function inSync(array $env, array $example): bool
    {
        return $this->missing($env, $example) === [];
    }
}
