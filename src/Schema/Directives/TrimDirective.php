<?php declare(strict_types=1);

namespace Lantern\Schema\Directives;

use Lantern\Execution\Arguments\ArgumentSet;
use Lantern\Schema\Values\FieldValue;
use Lantern\Support\Contracts\ArgDirective;
use Lantern\Support\Contracts\ArgSanitizerDirective;
use Lantern\Support\Contracts\FieldMiddleware;
use Lantern\Support\Utils;

class TrimDirective extends BaseDirective implements ArgSanitizerDirective, ArgDirective, FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Remove whitespace from the beginning and end of a given input.

This can be used on:
- a single argument or input field to sanitize that subtree
- a field to trim all strings
"""
directive @trim on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    /** Remove whitespace from the beginning and end of a given input. */
    public function sanitize(mixed $argumentValue): mixed
    {
        return Utils::mapEach(
            fn (mixed $value): mixed => $value instanceof ArgumentSet
                ? $this->transformArgumentSet($value)
                : $this->transformLeaf($value),
            $argumentValue,
        );
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->addArgumentSetTransformer(fn (ArgumentSet $argumentSet): ArgumentSet => $this->transformArgumentSet($argumentSet));
    }

    protected function transformArgumentSet(ArgumentSet $argumentSet): ArgumentSet
    {
        foreach ($argumentSet->arguments as $argument) {
            $argument->value = $this->sanitize($argument->value);
        }

        return $argumentSet;
    }

    /**
     * @param  mixed  $value  The client given value
     *
     * @return mixed The transformed value
     */
    protected function transformLeaf(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }
}
