<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Lantern\Exceptions\DefinitionException;
use Lantern\Execution\ModelsLoader\CountModelsLoader;
use Lantern\Execution\ModelsLoader\ModelsLoader;
use Lantern\Execution\ResolveInfo;
use Lantern\Schema\AST\DocumentAST;
use Lantern\Schema\RootType;
use Lantern\Support\Contracts\FieldManipulator;
use Lantern\Support\Contracts\GraphQLContext;

class WithCountDirective extends WithRelationDirective implements FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Eager-load the count of an Eloquent relation if the field is queried.

Note that this does not return a value for the field, the count is simply
prefetched, assuming it is used to compute the field value. Use `@count`
if the field should simply return the relation count.
"""
directive @withCount(
  """
  Specify the relationship method name in the model class.
  """
  relation: String!

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        if (RootType::isRootType($parentType->name->value)) {
            throw new DefinitionException("Can not use @{$this->name()} on fields of a root type.");
        }

        $relation = $this->directiveArgValue('relation');
        if (! is_string($relation)) {
            throw new DefinitionException("You must specify the argument relation in the {$this->name()} directive on {$this->definitionNode->name->value}.");
        }
    }

    /**
     * @param  array<string, mixed>  $args
     *
     * @return CountModelsLoader
     */
    protected function modelsLoader(mixed $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ModelsLoader
    {
        return new CountModelsLoader(
            $this->relation(),
            $this->makeBuilderDecorator($parent, $args, $context, $resolveInfo),
        );
    }
}
