<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Closure;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EnvKitException;

/**
 * The interactive TUI (`env:edit`): a browse/edit loop on laravel/prompts (the
 * UI layer of laranail/console). Pure orchestration — every mutation goes
 * through the same {@see EnvKit} engine, so atomicity/guards/audit/events all
 * apply. Engine errors are shown inline and the loop keeps running.
 */
final class EditCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.edit
        {--file= : operate on a custom .env file}
        {--force-production : allow writes in production}';

    /** @var string */
    protected $description = 'Interactively browse and edit the .env file.';

    /** @var list<string> */
    protected array $commandAliases = ['env:edit'];

    private const ADD = '＋ Add a new key';

    private const QUIT = 'Quit';

    private bool $force = false;

    public function handle(EnvKit $env): int
    {
        $this->force = (bool) $this->option('force-production');
        $target = $this->targetEnv($env);

        if ($this->laravel->environment('production')) {
            $this->warn('⚠  You are editing a PRODUCTION environment.');
        }

        while (true) {
            $choice = (string) select('Choose a key or action', [...$target->keys(), self::ADD, self::QUIT]);

            if ($choice === self::QUIT) {
                break;
            }

            $this->guard(fn () => $choice === self::ADD ? $this->addKey($target) : $this->editKey($target, $choice));
        }

        $this->info('Closed EnvKit editor.');

        return self::EXIT_OK;
    }

    /** Re-arm the production override before each mutation (each commit consumes it). */
    private function allowing(EnvKit $env): EnvKit
    {
        if ($this->force) {
            $env->allowProduction();
        }

        return $env;
    }

    private function addKey(EnvKit $env): void
    {
        $key = trim(text('New key name', required: true));
        $this->allowing($env)->set($key, text("Value for {$key}"));
        $this->info("Set [{$key}].");
    }

    private function editKey(EnvKit $env, string $key): void
    {
        match ((string) select("Edit [{$key}]", ['Edit value', 'Rename', 'Delete', 'Back'])) {
            'Edit value' => $this->editValue($env, $key),
            'Rename' => $this->renameKey($env, $key),
            'Delete' => $this->deleteKey($env, $key),
            default => null,
        };
    }

    private function editValue(EnvKit $env, string $key): void
    {
        $this->allowing($env)->set($key, text("New value for [{$key}]", default: $env->getString($key) ?? ''));
        $this->info("Updated [{$key}].");
    }

    private function renameKey(EnvKit $env, string $key): void
    {
        $to = trim(text("Rename [{$key}] to", required: true));
        $this->allowing($env)->rename($key, $to);
        $this->info("Renamed [{$key}] to [{$to}].");
    }

    private function deleteKey(EnvKit $env, string $key): void
    {
        if (confirm("Delete [{$key}]?", default: false)) {
            $this->allowing($env)->forget($key);
            $this->info("Removed [{$key}].");
        }
    }

    /** Run a menu action, surfacing engine errors inline without breaking the loop. */
    private function guard(Closure $action): void
    {
        try {
            $action();
        } catch (EnvKitException $e) {
            $this->error($e->getMessage()); // secret-safe: messages carry key names, never values
        }
    }
}
