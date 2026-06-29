<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\EnvKit\Headless\Contracts\DoctorRuleInterface;
use Simtabi\Laranail\EnvKit\Headless\Doctor\Diagnostic;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

/** Registers + tags a doctor rule during register(), before the engine's booted() seed runs. */
final class EnvKitTagSeedingProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('envkit.test.rule', fn () => new class implements DoctorRuleInterface
        {
            public function check(EnvDocument $document): array
            {
                return [new Diagnostic('info', 'tagged-rule-ran')];
            }
        });

        $this->app->tag(['envkit.test.rule'], 'env-kit.doctor_rules');
    }
}

class EnvKitTaggingTestCase extends TestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), EnvKitTagSeedingProvider::class];
    }
}

uses(EnvKitTaggingTestCase::class);

it('picks up a container-tagged doctor rule in inspect()', function () {
    $this->bindEnv("A=1\n");

    $messages = array_map(fn (Diagnostic $d): string => $d->message, EnvKit::inspect());

    expect($messages)->toContain('tagged-rule-ran');
});
