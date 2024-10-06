<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lantern\Execution\Arguments\ArgumentSet;
use Lantern\Execution\Arguments\ResolveNested;
use Lantern\Execution\TransactionalMutations;
use Lantern\Support\Contracts\ArgResolver;
use Lantern\Support\Contracts\FieldResolver;
use Lantern\Support\Utils;

abstract class ModelMutationDirective extends BaseDirective implements FieldResolver, ArgResolver
{
    public function __construct(
        protected TransactionalMutations $transactionalMutations,
    ) {}

    /**
     * @param  Model  $model
     * @param  \Lantern\Execution\Arguments\ArgumentSet|array<\Lantern\Execution\Arguments\ArgumentSet>  $args
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    public function __invoke($model, $args): mixed
    {
        $relationName = $this->directiveArgValue(
            'relation',
            // Use the name of the argument if no explicit relation name is given
            $this->nodeName(),
        );

        $relation = $model->{$relationName}();
        assert($relation instanceof Relation);

        // @phpstan-ignore-next-line Relation&Builder mixin not recognized
        $related = $relation->make();
        assert($related instanceof Model);

        return $this->executeMutation($related, $args, $relation);
    }

    /**
     * @param  \Lantern\Execution\Arguments\ArgumentSet|array<\Lantern\Execution\Arguments\ArgumentSet>  $args
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $parentRelation
     *
     * @return \Illuminate\Database\Eloquent\Model|array<\Illuminate\Database\Eloquent\Model>
     */
    protected function executeMutation(Model $model, ArgumentSet|array $args, ?Relation $parentRelation = null): Model|array
    {
        $update = new ResolveNested($this->makeExecutionFunction($parentRelation));

        return Utils::mapEach(
            static fn (ArgumentSet $argumentSet): mixed => $update($model->newInstance(), $argumentSet),
            $args,
        );
    }

    /**
     * Prepare the execution function for a mutation on a model.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $parentRelation
     */
    abstract protected function makeExecutionFunction(?Relation $parentRelation = null): callable;
}
