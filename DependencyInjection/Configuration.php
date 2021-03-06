<?php

namespace Liip\ImagineBundle\DependencyInjection;

use Liip\ImagineBundle\DependencyInjection\Factory\Loader\LoaderFactoryInterface;
use Liip\ImagineBundle\DependencyInjection\Factory\Resolver\ResolverFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var ResolverFactoryInterface[]
     */
    protected $resolversFactories;

    /**
     * @var LoaderFactoryInterface[]
     */
    protected $loadersFactories;

    /**
     * @param ResolverFactoryInterface[] $resolversFactories
     * @param LoaderFactoryInterface[] $loadersFactories
     */
    public function __construct(array $resolversFactories, array $loadersFactories)
    {
        $this->resolversFactories = $resolversFactories;
        $this->loadersFactories = $loadersFactories;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('liip_imagine', 'array');

        $resolversPrototypeNode = $rootNode
            ->children()
                ->arrayNode('resolvers')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
        ;
        $this->addResolversSections($resolversPrototypeNode);

        $loadersPrototypeNode = $rootNode
            ->children()
                ->arrayNode('loaders')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
        ;
        $this->addLoadersSections($loadersPrototypeNode);

        $rootNode
            ->beforeNormalization()
                ->ifTrue(function ($v) {
                    return
                        empty($v['loaders']) ||
                        empty($v['loaders']['default']) ||
                        empty($v['resolvers']) ||
                        empty($v['resolvers']['default'])
                    ;
                })
                ->then(function ($v) {
                    if (empty($v['loaders'])) {
                        $v['loaders'] = array();
                    }

                    if (false == is_array($v['loaders'])) {
                        throw new \LogicException('Loaders has to be array');
                    }

                    if (false == array_key_exists('default', $v['loaders'])) {
                        $v['loaders']['default'] = array('filesystem' => null);
                    }

                    if (empty($v['resolvers'])) {
                        $v['resolvers'] = array();
                    }

                    if (false == is_array($v['resolvers'])) {
                        throw new \LogicException('Resolvers has to be array');
                    }

                    if (false == array_key_exists('default', $v['resolvers'])) {
                        $v['resolvers']['default'] = array('web_path' => null);
                    }

                    return $v;
                })
            ->end()
        ;

        $rootNode
            ->fixXmlConfig('filter_set', 'filter_sets')
            ->children()
                ->scalarNode('driver')->defaultValue('gd')
                    ->validate()
                        ->ifTrue(function($v) { return !in_array($v, array('gd', 'imagick', 'gmagick')); })
                        ->thenInvalid('Invalid imagine driver specified: %s')
                    ->end()
                ->end()
                ->scalarNode('cache')->defaultValue('default')->end()
                ->scalarNode('cache_base_path')->defaultValue('')->end()
                ->scalarNode('data_loader')->defaultValue('default')->end()
                ->scalarNode('controller_action')->defaultValue('liip_imagine.controller:filterAction')->end()
                ->arrayNode('filter_sets')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->fixXmlConfig('filter', 'filters')
                        ->children()
                            ->scalarNode('quality')->defaultValue(100)->end()
                            ->scalarNode('cache')->defaultNull()->end()
                            ->scalarNode('data_loader')->defaultNull()->end()
                            ->scalarNode('controller_action')->defaultNull()->end()
                            ->arrayNode('route')
                                ->defaultValue(array())
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->useAttributeAsKey('name')
                                    ->prototype('variable')->end()
                                ->end()
                            ->end()
                            ->arrayNode('filters')
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->useAttributeAsKey('name')
                                    ->prototype('variable')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $resolversPrototypeNode
     */
    protected function addResolversSections(ArrayNodeDefinition $resolversPrototypeNode)
    {
        foreach ($this->resolversFactories as $factory) {
            $factory->addConfiguration(
                $resolversPrototypeNode->children()->arrayNode($factory->getName())
            );
        }
    }

    /**
     * @param ArrayNodeDefinition $resolversPrototypeNode
     */
    protected function addLoadersSections(ArrayNodeDefinition $resolversPrototypeNode)
    {
        foreach ($this->loadersFactories as $factory) {
            $factory->addConfiguration(
                $resolversPrototypeNode->children()->arrayNode($factory->getName())
            );
        }
    }
}
