<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Lantern\Exceptions\DefinitionException;
use Lantern\Execution\ModelsLoader\ModelsLoader;
use Lantern\Execution\ModelsLoader\SimpleModelsLoader;
use Lantern\Execution\ResolveInfo;
use Lantern\Schema\AST\DocumentAST;
use Lantern\Schema\RootType;
use Lantern\Support\Contracts\FieldManipulator;
use Lantern\Support\Contracts\GraphQLContext;

class WithDirective extends WithRelationDirective implements FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Eager-load an Eloquent relation.
"""
directive @with(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

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
    }

    /** @return SimpleModelsLoader */
    protected function modelsLoader(mixed $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ModelsLoader
    {
        return new SimpleModelsLoader(
            $this->relation(),
            $this->makeBuilderDecorator($parent, $args, $context, $resolveInfo),
        );
    }
}
