<?php

/*
 * This file is part of the OverblogGraphQLBundle package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\GraphQLBundle\Relay\Node;

use Overblog\GraphQLBundle\Definition\Builder\MappingInterface;

class PluralIdentifyingRootFieldDefinition implements MappingInterface
{
    public function toMappingDefinition(array $config)
    {
        if (!isset($config['argName']) || !is_string($config['argName'])) {
            throw new \InvalidArgumentException('A valid pluralIdentifyingRoot "argName" config is required.');
        }

        if (!isset($config['inputType']) || !is_string($config['inputType'])) {
            throw new \InvalidArgumentException('A valid pluralIdentifyingRoot "inputType" config is required.');
        }

        if (!isset($config['outputType']) || !is_string($config['outputType'])) {
            throw new \InvalidArgumentException('A valid pluralIdentifyingRoot "outputType" config is required.');
        }

        if (!array_key_exists('resolveSingleInput', $config)) {
            throw new \InvalidArgumentException('PluralIdentifyingRoot "resolveSingleInput" config is required.');
        }

        $argName = $config['argName'];

        return [
            'type' => "[${config['outputType']}]",
            'args' => [$argName => ['type' => "[${config['inputType']}!]!"]],
            'resolve' => sprintf(
                "@=resolver('relay_plural_identifying_field', [args['$argName'], resolveSingleInputCallback(%s)])",
                $this->cleanResolveSingleInput($config['resolveSingleInput'])
            ),
        ];
    }

    private function cleanResolveSingleInput($resolveSingleInput)
    {
        if (is_string($resolveSingleInput) &&  0 === strpos($resolveSingleInput, '@=')) {
            $cleanResolveSingleInput = substr($resolveSingleInput, 2);
        } else {
            $cleanResolveSingleInput = json_encode($resolveSingleInput);
        }

        return $cleanResolveSingleInput;
    }
}
