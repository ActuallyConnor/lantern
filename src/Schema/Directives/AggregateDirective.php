<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use GraphQL\Deferred;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lantern\Exceptions\DefinitionException;
use Lantern\Execution\BatchLoader\BatchLoaderRegistry;
use Lantern\Execution\BatchLoader\RelationBatchLoader;
use Lantern\Execution\ModelsLoader\AggregateModelsLoader;
use Lantern\Execution\ResolveInfo;
use Lantern\Schema\AST\DocumentAST;
use Lantern\Schema\Values\FieldValue;
use Lantern\Support\Contracts\FieldManipulator;
use Lantern\Support\Contracts\FieldResolver;
use Lantern\Support\Contracts\GraphQLContext;

class AggregateDirective extends BaseDirective implements FieldResolver, FieldManipulator
{
    use RelationDirectiveHelpers;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Returns an aggregate of a column in a given relationship or model.
"""
directive @aggregate(
  """
  The column to aggregate.
  """
  column: String!

  """
  The aggregate function to compute.
  """
  function: AggregateFunction!

  """
  The relationship with the column to aggregate.
  Mutually exclusive with `model` and `builder`.
  """
  relation: String

  """
  The model with the column to aggregate.
  Mutually exclusive with `relation` and `builder`.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  Mutually exclusive with `relation` and `model`.
  """
  builder: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION

"""
Options for the `function` argument of `@aggregate`.
"""
enum AggregateFunction {
  """
  Return the average value.
  """
  AVG

  """
  Return the sum.
  """
  SUM

  """
  Return the minimum.
  """
  MIN

  """
  Return the maximum.
  """
  MAX
}
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        $modelArg = $this->directiveArgValue('model');
        if (is_string($modelArg)) {
            return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelArg) {
                $builder = $this->namespaceModelClass($modelArg)::query();

                $this->makeBuilderDecorator($root, $args, $context, $resolveInfo)($builder);

                return $builder->{$this->function()}($this->column());
            };
        }

        $relation = $this->directiveArgValue('relation');
        if (is_string($relation)) {
            return function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred {
                $relationBatchLoader = BatchLoaderRegistry::instance(
                    [...$this->qualifyPath($args, $resolveInfo), $this->function(), $this->column()],
                    fn (): RelationBatchLoader => new RelationBatchLoader(
                        new AggregateModelsLoader(
                            $this->relation(),
                            $this->column(),
                            $this->function(),
                            $this->makeBuilderDecorator($parent, $args, $context, $resolveInfo),
                        ),
                    ),
                );

                return $relationBatchLoader->load($parent);
            };
        }

        $modelArg = $this->directiveArgValue('model');
        if (is_string($modelArg)) {
            return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelArg) {
                $query = $this->namespaceModelClass($modelArg)::query();

                $this->makeBuilderDecorator($root, $args, $context, $resolveInfo)($query);

                return $query->{$this->function()}($this->column());
            };
        }

        if ($this->directiveHasArgument('builder')) {
            $builderResolver = $this->getResolverFromArgument('builder');

            return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($builderResolver) {
                $builder = $builderResolver($root, $args, $context, $resolveInfo);

                assert(
                    $builder instanceof QueryBuilder || $builder instanceof EloquentBuilder,
                    "The method referenced by the builder argument of the @{$this->name()} directive on {$this->nodeName()} must return a Builder.",
                );

                $this->makeBuilderDecorator($root, $args, $context, $resolveInfo)($builder);

                return $builder->{$this->function()}($this->column());
            };
        }

        throw new DefinitionException("One of the arguments `model`, `relation` or `builder` must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}'.");
    }

    protected function function(): string
    {
        return strtolower(
            $this->directiveArgValue('function'),
        );
    }

    protected function column(): string
    {
        return $this->directiveArgValue('column');
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $this->validateMutuallyExclusiveArguments(['relation', 'model', 'builder']);
    }
}
