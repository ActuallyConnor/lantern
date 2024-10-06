<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lantern\Execution\Arguments\SaveModel;
use Lantern\Execution\Arguments\UpdateModel;

class UpdateDirective extends OneModelMutationDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Update an Eloquent model with the given arguments.
"""
directive @update(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    protected function makeExecutionFunction(?Relation $parentRelation = null): callable
    {
        return new UpdateModel(new SaveModel($parentRelation));
    }
}
