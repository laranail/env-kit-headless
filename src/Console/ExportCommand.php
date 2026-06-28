<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class ExportCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.export
        {--format=json : json or csv}
        {--file= : operate on a custom .env file}
        {--output= : write to this file instead of stdout}';

    /** @var string */
    protected $description = 'Export the .env values as json or csv.';

    /** @var list<string> */
    protected array $commandAliases = ['env:export'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $output = $this->targetEnv($env)->export($this->formatOption());

            $destination = $this->option('output');
            if (is_string($destination) && $destination !== '') {
                file_put_contents($destination, $output);
                $this->info("Exported to [{$destination}].");
            } else {
                $this->line($output);
            }

            return self::EXIT_OK;
        });
    }
}
