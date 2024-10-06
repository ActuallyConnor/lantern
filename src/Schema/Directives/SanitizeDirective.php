<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use Lantern\Support\Contracts\ArgSanitizerDirective;
use Lantern\Support\Contracts\Directive;

class SanitizeDirective extends ArgTraversalDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Apply sanitization to the arguments of a field.
"""
directive @sanitize on FIELD_DEFINITION
GRAPHQL;
    }

    protected function applyDirective(Directive $directive, mixed $value): mixed
    {
        if ($directive instanceof ArgSanitizerDirective) {
            return $directive->sanitize($value);
        }

        return $value;
    }
}
