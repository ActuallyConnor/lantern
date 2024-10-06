<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use Lantern\Execution\Arguments\ArgumentSet;
use Lantern\Execution\Arguments\ResolveNested;
use Lantern\Support\Contracts\ArgResolver;
use Lantern\Support\Utils;

class NestDirective extends BaseDirective implements ArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
A no-op nested arg resolver that delegates all calls
to the ArgResolver directives attached to the children.
"""
directive @nest on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Delegate to nested arg resolvers.
     *
     * @param  \Lantern\Execution\Arguments\ArgumentSet|array<\Lantern\Execution\Arguments\ArgumentSet>  $args  the slice of arguments that belongs to this nested resolver
     */
    public function __invoke(mixed $root, $args): mixed
    {
        $resolveNested = new ResolveNested();

        return Utils::mapEach(
            static fn (ArgumentSet $argumentSet): mixed => $resolveNested($root, $argumentSet),
            $args,
        );
    }
}
