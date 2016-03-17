<?php

/*
 * This file is part of the OverblogGraphQLBundle package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\GraphQLBundle\Resolver\Config;

use Overblog\GraphQLBundle\Definition\Builder\MappingInterface;
use Overblog\GraphQLBundle\Error\UserError;
use Overblog\GraphQLBundle\Relay\Connection\Output\Connection;
use Overblog\GraphQLBundle\Relay\Connection\Output\Edge;
use Overblog\GraphQLBundle\Resolver\ResolverInterface;

class FieldsConfigSolution extends AbstractConfigSolution implements UniqueConfigSolutionInterface
{
    /**
     * @var TypeConfigSolution
     */
    private $typeConfigSolution;

    /**
     * @var ResolveCallbackConfigSolution
     */
    private $resolveCallbackConfigSolution;

    public function __construct(
        TypeConfigSolution $typeConfigSolution,
        ResolveCallbackConfigSolution $resolveCallbackConfigSolution
    ) {
        $this->typeConfigSolution = $typeConfigSolution;
        $this->resolveCallbackConfigSolution = $resolveCallbackConfigSolution;
    }

    public function solve($values, $config = null)
    {
        // builder must be last
        $fieldsTreated = ['type', 'args', 'argsBuilder', 'deprecationReason', 'builder'];

        foreach ($values as $field => &$options) {
            foreach ($fieldsTreated as $fieldTreated) {
                if (isset($options[$fieldTreated])) {
                    $method = 'solve'.ucfirst($fieldTreated);
                    $options = $this->$method($options, $field);
                }
            }

            $options = $this->resolveResolveAndAccessIfNeeded($options);
        }

        return $values;
    }

    private function solveBuilder($options, $field)
    {
        $builderConfig = isset($options['builderConfig']) ? $options['builderConfig'] : [];

        $access = isset($options['access']) ? $options['access'] : null;
        $options = $this->builderToMappingDefinition($options['builder'], $builderConfig, $this->fieldResolver, $field);
        $options['access'] = $access;
        $options = $this->resolveResolveAndAccessIfNeeded($options);

        unset($options['builderConfig'], $options['builder']);

        return $options;
    }

    private function solveType($options)
    {
        $options['type'] = $this->typeConfigSolution->solveTypeCallback($options['type']);

        return $options;
    }

    private function solveArgs($options)
    {
        foreach ($options['args'] as &$argsOptions) {
            $argsOptions['type'] = $this->typeConfigSolution->solveTypeCallback($argsOptions['type']);
            if (isset($argsOptions['defaultValue'])) {
                $argsOptions['defaultValue'] = $this->solveUsingExpressionLanguageIfNeeded($argsOptions['defaultValue']);
            }
        }

        return $options;
    }

    private function solveArgsBuilder($options)
    {
        $argsBuilderConfig = isset($options['argsBuilder']['config']) ? $options['argsBuilder']['config'] : [];

        $options['args'] = array_merge(
            $this->builderToMappingDefinition($options['argsBuilder']['builder'], $argsBuilderConfig, $this->argResolver),
            isset($options['args']) ? $options['args'] : []
        );

        unset($options['argsBuilder']);

        return $options;
    }

    private function solveDeprecationReason($options)
    {
        $options['deprecationReason'] = $this->solveUsingExpressionLanguageIfNeeded($options['deprecationReason']);

        return $options;
    }

    private function builderToMappingDefinition($rawBuilder, array $rawBuilderConfig, ResolverInterface $builderResolver, $name = null)
    {
        /** @var MappingInterface $builder */
        $builder = $builderResolver->resolve($rawBuilder);
        $builderConfig = [];
        if (!empty($rawBuilderConfig)) {
            $builderConfig = $rawBuilderConfig;
            $builderConfig = $this->configResolver->resolve($builderConfig);
        }

        if (null !== $name) {
            $builderConfig['name'] = $name;
        }

        return $builder->toMappingDefinition($builderConfig);
    }

    private function resolveResolveAndAccessIfNeeded(array $options)
    {
        $treatedOptions = $options;

        if (isset($treatedOptions['resolve'])) {
            $treatedOptions['resolve'] = $this->resolveCallbackConfigSolution->solve($treatedOptions['resolve']);
        }

        if (isset($treatedOptions['access'])) {
            $resolveCallback = $this->configResolver->getDefaultResolveFn();

            if (isset($treatedOptions['resolve'])) {
                $resolveCallback = $treatedOptions['resolve'];
            }

            $treatedOptions['resolve'] = $this->resolveAccessAndWrapResolveCallback($treatedOptions['access'], $resolveCallback);
        }
        unset($treatedOptions['access']);

        return $treatedOptions;
    }

    private function resolveAccessAndWrapResolveCallback($expression, callable $resolveCallback = null)
    {
        return function () use ($expression, $resolveCallback) {
            $args = func_get_args();

            $result = null !== $resolveCallback  ? call_user_func_array($resolveCallback, $args) : null;

            $values = call_user_func_array([$this, 'solveResolveCallbackArgs'], $args);

            return $this->filterResultUsingAccess($result, $expression, $values);
        };
    }

    private function filterResultUsingAccess($result, $expression, $values)
    {
        $checkAccess = $this->checkAccessCallback($expression, $values);

        switch (true) {
            case is_array($result) || $result instanceof \ArrayAccess:
                $result = array_filter(
                    array_map(
                        function ($object) use ($checkAccess) {
                            return $checkAccess($object) ? $object : null;
                        },
                        $result
                    )
                );
                break;

            case $result instanceof Connection:
                $result->edges = array_map(
                    function (Edge $edge) use ($checkAccess) {
                        $edge->node = $checkAccess($edge->node) ? $edge->node : null;

                        return $edge;
                    },
                    $result->edges
                );
                break;

            default:
                $checkAccess($result, true);
                break;
        }

        return $result;
    }

    private function checkAccessCallback($expression, $values)
    {
        return function ($object, $throwException = false) use ($expression, $values) {
            try {
                $access = $this->solveUsingExpressionLanguageIfNeeded(
                    $expression,
                    array_merge($values, ['object' => $object])
                );
            } catch (\Exception $e) {
                $access = false;
            }

            if ($throwException && !$access) {
                throw new UserError('Access denied to this field.');
            }

            return $access;
        };
    }
}