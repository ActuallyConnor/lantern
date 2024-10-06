<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lantern\Execution\ResolveInfo;
use Lantern\Schema\Values\FieldValue;
use Lantern\Support\Contracts\FieldResolver;
use Lantern\Support\Contracts\GraphQLContext;

class FirstDirective extends BaseDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Get the first query result from a collection of Eloquent models.
"""
directive @first(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?Model {
            $builder = $resolveInfo->enhanceBuilder(
                $this->getModelClass()::query(),
                $this->directiveArgValue('scopes', []),
                $root,
                $args,
                $context,
                $resolveInfo,
            );
            assert($builder instanceof EloquentBuilder);

            return $builder->first();
        };
    }
}
