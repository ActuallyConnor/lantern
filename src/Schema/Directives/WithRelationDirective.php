<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use Illuminate\Database\Eloquent\Model;
use Lantern\Execution\BatchLoader\BatchLoaderRegistry;
use Lantern\Execution\BatchLoader\RelationBatchLoader;
use Lantern\Execution\ModelsLoader\ModelsLoader;
use Lantern\Execution\ResolveInfo;
use Lantern\Schema\Values\FieldValue;
use Lantern\Support\Contracts\FieldMiddleware;
use Lantern\Support\Contracts\GraphQLContext;

abstract class WithRelationDirective extends BaseDirective implements FieldMiddleware
{
    use RelationDirectiveHelpers;

    /** @param  array<string, mixed>  $args */
    abstract protected function modelsLoader(mixed $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ModelsLoader;

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(
            fn (callable $resolver): \Closure => fn (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): SyncPromise => $this
                ->loadRelation($parent, $args, $context, $resolveInfo)
                ->then(static fn (): mixed => $resolver($parent, $args, $context, $resolveInfo)),
        );
    }

    /** @param  array<string, mixed>  $args */
    protected function loadRelation(Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred
    {
        $relationBatchLoader = BatchLoaderRegistry::instance(
            $this->qualifyPath($args, $resolveInfo),
            fn (): RelationBatchLoader => new RelationBatchLoader($this->modelsLoader($parent, $args, $context, $resolveInfo)),
        );

        return $relationBatchLoader->load($parent);
    }
}
