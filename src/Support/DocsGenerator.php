<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Support;

use Simtabi\Laranail\EnvKit\Headless\Schema\EnvSchema;

/** Renders a resolved {@see EnvSchema} as a Markdown reference table (for `env:docs`). */
final class DocsGenerator
{
    public function generate(EnvSchema $schema): string
    {
        $described = $schema->describe();

        if ($described === []) {
            return "# Environment schema\n\n_No schema rules are defined._\n";
        }

        $markdown = "# Environment schema\n\n| Key | Rules |\n|-----|-------|\n";
        foreach ($described as $key => $rules) {
            $markdown .= '| `'.$key.'` | '.implode(', ', $rules)." |\n";
        }

        return $markdown;
    }
}
