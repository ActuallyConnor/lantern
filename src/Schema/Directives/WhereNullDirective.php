<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lantern\Execution\ResolveInfo;
use Lantern\Support\Contracts\ArgBuilderDirective;
use Lantern\Support\Contracts\FieldBuilderDirective;
use Lantern\Support\Contracts\GraphQLContext;

class WhereNullDirective extends BaseDirective implements ArgBuilderDirective, FieldBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Filter the value is null.
"""
directive @whereNull(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String

  """
  Should the value be null?
  Exclusively required when this directive is used on a field.
  """
  value: Boolean
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        if ($value === null) {
            return $builder;
        }

        return $builder->whereNull(
            $this->directiveArgValue('key', $this->nodeName()),
            'and',
            ! $value,
        );
    }

    public function handleFieldBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): QueryBuilder|EloquentBuilder|Relation
    {
        return $this->handleBuilder(
            $builder,
            $this->directiveArgValue('value'),
        );
    }
}
