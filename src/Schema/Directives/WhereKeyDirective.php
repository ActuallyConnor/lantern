<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lantern\Exceptions\DefinitionException;
use Lantern\Execution\ResolveInfo;
use Lantern\Schema\AST\DocumentAST;
use Lantern\Support\Contracts\ArgBuilderDirective;
use Lantern\Support\Contracts\FieldBuilderDirective;
use Lantern\Support\Contracts\FieldManipulator;
use Lantern\Support\Contracts\GraphQLContext;

class WhereKeyDirective extends BaseDirective implements ArgBuilderDirective, FieldBuilderDirective, FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Add a where clause on the primary key to the Eloquent Model query.
"""
directive @whereKey(
  """
  Provide a value to compare against.
  Exclusively required when this directive is used on a field.
  """
  value: WhereKeyValue
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar WhereKeyValue
GRAPHQL;
    }

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        if (! $builder instanceof EloquentBuilder) {
            $notEloquentBuilder = $builder::class;
            throw new DefinitionException("The {$this->name()} directive only works with queries that use an Eloquent builder, got: {$notEloquentBuilder}.");
        }

        return $builder->whereKey($value);
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        if (! $this->directiveHasArgument('value')) {
            throw new DefinitionException("Must provide the argument `value` when using {$this->name()} on field `{$parentType->name->value}.{$fieldDefinition->name->value}`.");
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
