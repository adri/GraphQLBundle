<?php

namespace Tests\Overblog\GraphBundle\Resolver;

use Overblog\GraphBundle\Resolver\FieldResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class FieldResolverTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ContainerBuilder */
    private static $container;

    /** @var  FieldResolver */
    private static $fieldResolver;

    public static function setUpBeforeClass()
    {
        $container = new ContainerBuilder();

        $mapping = [
            'Toto' => ['id' => 'overblog_graph.definition.custom_toto_field', 'alias' => 'Toto'],
            'Tata' => ['id' => 'overblog_graph.definition.custom_tata_field', 'alias' => 'Tata'],
        ];

        $container->setParameter('overblog_graph.fields_mapping', $mapping);

        foreach($mapping as $alias => $options) {
            $container->setDefinition($options['id'], new Definition('stdClass'))
                ->setProperty('name', $alias);
        }

        self::$container = $container;
        self::$fieldResolver = new FieldResolver(self::$container);
    }

    public function testResolveKnownField()
    {
        $field = self::$fieldResolver->resolve('Toto');

        $this->assertInstanceOf('stdClass', $field);
        $this->assertEquals('Toto', $field->name);
    }

    /**
     * @expectedException \Overblog\GraphBundle\Resolver\UnresolvableException
     */
    public function testResolveUnknownField()
    {
        self::$fieldResolver->resolve('Fake');
    }
}
