<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Lantern\Exceptions\DefinitionException;
use Lantern\Execution\ResolveInfo;
use Lantern\Schema\AST\DocumentAST;
use Lantern\Scout\ScoutBuilderDirective;
use Lantern\Support\Contracts\ArgBuilderDirective;
use Lantern\Support\Contracts\FieldBuilderDirective;
use Lantern\Support\Contracts\FieldManipulator;
use Lantern\Support\Contracts\GraphQLContext;

class EqDirective extends BaseDirective implements ArgBuilderDirective, ScoutBuilderDirective, FieldBuilderDirective, FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Add an equal conditional to a database query.
"""
directive @eq(
  """
  Specify the database column to compare.
  Required if the directive is:
  - used on an argument and the database column has a different name
  - used on a field
  """
  key: String

  """
  Provide a value to compare against.
  Exclusively required when this directive is used on a field.
  """
  value: EqValue
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar EqValue
GRAPHQL;
    }

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        return $builder->where(
            $this->directiveArgValue('key', $this->nodeName()),
            $value,
        );
    }

    public function handleScoutBuilder(ScoutBuilder $builder, mixed $value): ScoutBuilder
    {
        return $builder->where(
            $this->directiveArgValue('key', $this->nodeName()),
            $value,
        );
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        if (! $this->directiveHasArgument('value')) {
            throw new DefinitionException("Must provide the argument `value` when using {$this->name()} on field `{$parentType->name->value}.{$fieldDefinition->name->value}`.");
        }

        if (! $this->directiveHasArgument('key')) {
            throw new DefinitionException("Must provide the argument `key` when using {$this->name()} on field `{$parentType->name->value}.{$fieldDefinition->name->value}`.");
        }
    }

    public function handleFieldBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): QueryBuilder|EloquentBuilder|Relation
    {
        return $this->handleBuilder(
            $builder,
            $this->directiveArgValue('value'),
        );
    }
}
