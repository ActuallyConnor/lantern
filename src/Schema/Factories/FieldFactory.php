<?php declare(strict_types=1);

namespace Lantern\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Lantern\Execution\Arguments\ArgumentSetFactory;
use Lantern\Schema\AST\ASTHelper;
use Lantern\Schema\AST\ExecutableTypeNodeConverter;
use Lantern\Schema\DirectiveLocator;
use Lantern\Schema\Directives\BaseDirective;
use Lantern\Schema\RootType;
use Lantern\Schema\Values\FieldValue;
use Lantern\Support\Contracts\ComplexityResolverDirective;
use Lantern\Support\Contracts\Directive;
use Lantern\Support\Contracts\FieldMiddleware;
use Lantern\Support\Contracts\FieldResolver;
use Lantern\Support\Contracts\ProvidesResolver;
use Lantern\Support\Contracts\ProvidesSubscriptionResolver;

/**
 * @phpstan-import-type FieldResolver from \GraphQL\Executor\Executor as FieldResolverFn
 * @phpstan-import-type FieldDefinitionConfig from \GraphQL\Type\Definition\FieldDefinition
 * @phpstan-import-type FieldType from \GraphQL\Type\Definition\FieldDefinition
 * @phpstan-import-type ComplexityFn from \GraphQL\Type\Definition\FieldDefinition
 */
class FieldFactory
{
    public function __construct(
        protected ConfigRepository $config,
        protected DirectiveLocator $directiveLocator,
        protected ArgumentFactory $argumentFactory,
        protected ArgumentSetFactory $argumentSetFactory,
    ) {}

    /**
     * Convert a FieldValue to a config for an executable FieldDefinition.
     *
     * @return FieldDefinitionConfig
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldDefinitionNode = $fieldValue->getField();

        // Directives have the first priority for defining a resolver for a field
        $resolverDirective = $this->directiveLocator->exclusiveOfType($fieldDefinitionNode, FieldResolver::class);
        $resolver = $resolverDirective instanceof FieldResolver
            ? $resolverDirective->resolveField($fieldValue)
            : $this->defaultResolver($fieldValue);

        foreach ($this->fieldMiddleware($fieldDefinitionNode) as $fieldMiddleware) {
            $fieldMiddleware->handleField($fieldValue);
        }

        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $this->type($fieldDefinitionNode),
            'args' => $this->argumentFactory->toTypeMap(
                $fieldValue->getField()->arguments,
            ),
            'resolve' => $fieldValue->finishResolver($resolver),
            'description' => $fieldDefinitionNode->description?->value,
            'complexity' => $this->complexity($fieldValue),
            'deprecationReason' => ASTHelper::deprecationReason($fieldDefinitionNode),
            'astNode' => $fieldDefinitionNode,
        ];
    }

    /** @return array<\Lantern\Support\Contracts\FieldMiddleware> */
    protected function fieldMiddleware(FieldDefinitionNode $fieldDefinitionNode): array
    {
        $globalFieldMiddleware = (new Collection($this->config->get('lighthouse.field_middleware')))
            ->map(static fn (string $middlewareDirective): Directive => Container::getInstance()->make($middlewareDirective))
            ->each(static function (Directive $directive) use ($fieldDefinitionNode): void {
                if ($directive instanceof BaseDirective) {
                    $directive->definitionNode = $fieldDefinitionNode;
                }
            })
            ->all();

        $directiveFieldMiddleware = $this->directiveLocator
            ->associatedOfType($fieldDefinitionNode, FieldMiddleware::class)
            ->all();

        // @phpstan-ignore-next-line PHPStan does not get this list is filtered for FieldMiddleware
        return array_merge($globalFieldMiddleware, $directiveFieldMiddleware);
    }

    /** @return \Closure(): (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\OutputType) */
    protected function type(FieldDefinitionNode $fieldDefinition): \Closure
    {
        return static function () use ($fieldDefinition) {
            $typeNodeConverter = Container::getInstance()->make(ExecutableTypeNodeConverter::class);

            return $typeNodeConverter->convert($fieldDefinition->type);
        };
    }

    /** @return ComplexityFn|null */
    protected function complexity(FieldValue $fieldValue): ?callable
    {
        $complexityDirective = $this->directiveLocator->exclusiveOfType(
            $fieldValue->getField(),
            ComplexityResolverDirective::class,
        );

        return $complexityDirective instanceof ComplexityResolverDirective
            ? $complexityDirective->complexityResolver($fieldValue)
            : null;
    }

    /** @return FieldResolverFn */
    protected function defaultResolver(FieldValue $fieldValue): callable
    {
        if ($fieldValue->getParentName() === RootType::SUBSCRIPTION) {
            /** @var ProvidesSubscriptionResolver $providesSubscriptionResolver */
            $providesSubscriptionResolver = Container::getInstance()->make(ProvidesSubscriptionResolver::class);

            return $providesSubscriptionResolver->provideSubscriptionResolver($fieldValue);
        }

        /** @var ProvidesResolver $providesResolver */
        $providesResolver = Container::getInstance()->make(ProvidesResolver::class);

        return $providesResolver->provideResolver($fieldValue);
    }
}
