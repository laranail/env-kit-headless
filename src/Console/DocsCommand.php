<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Support\DocsGenerator;

final class DocsCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.docs
        {--output= : write the markdown to a file instead of stdout}';

    /** @var string */
    protected $description = 'Render the resolved validation schema as a Markdown reference table.';

    /** @var list<string> */
    protected array $commandAliases = ['env:docs'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $markdown = (new DocsGenerator)->generate($env->schema());

            $output = $this->option('output');
            if (is_string($output) && $output !== '') {
                file_put_contents($output, $markdown);
                $this->info("Wrote schema docs to [{$output}].");

                return self::EXIT_OK;
            }

            $this->line($markdown);

            return self::EXIT_OK;
        });
    }
}
