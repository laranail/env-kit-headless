<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Contracts;

use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteContext;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteDecision;

/**
 * Authorizes a pending commit. The shipped default is environment-aware (lenient
 * outside production, strict in it); consumers swap it (`configure()->useUpdateGate`)
 * or wrap it (`configure()->decorateUpdateGate`) at runtime.
 */
interface UpdateGateInterface
{
    public function inspect(WriteContext $context): WriteDecision;
}
