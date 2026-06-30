<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Security;

/**
 * The single source of the persistent production warning shown on every surface
 * (CLI, TUI, WebUI) whenever `APP_ENV=production` — so operators always know they
 * are looking at live secrets, even when writes are permitted.
 */
final class ProductionBanner
{
    public const MESSAGE = 'PRODUCTION — you are viewing/editing live secrets. Writes are blocked unless explicitly forced.';

    public static function line(): string
    {
        return self::MESSAGE;
    }
}
