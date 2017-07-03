<?php

/*
 * This file is part of the Arachne
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\ServiceCollections\DI;

use Nette\DI\CompilerExtension;
use Nette\Utils\AssertionException;

/**
 * @author Jáchym Toušek <enumag@gmail.com>
 */
class ServiceCollectionsExtension extends CompilerExtension
{
    const TYPE_RESOLVER = 1;
    const TYPE_ITERATOR = 2;
    const TYPE_ITERATOR_RESOLVER = 3;

    const ATTRIBUTE_RESOLVER = 'arachne.service_collections.resolver';
    const ATTRIBUTE_ITERATOR_RESOLVER = 'arachne.service_collections.iterator_resolver';

    /**
     * @var array
     */
    private $services = [
        self::TYPE_RESOLVER => [],
        self::TYPE_ITERATOR => [],
        self::TYPE_ITERATOR_RESOLVER => [],
    ];

    /**
     * @var array
     */
    private $overrides = [
        self::TYPE_RESOLVER => [],
        self::TYPE_ITERATOR => [],
        self::TYPE_ITERATOR_RESOLVER => [],
    ];

    /**
     * @param int    $type
     * @param string $tag
     * @param string $implement
     *
     * @return string
     */
    public function getCollection($type, $tag, $implement = null)
    {
        if (isset($this->overrides[$type][$tag])) {
            return $this->overrides[$type][$tag];
        }

        if ($implement && isset($this->services[$type][$tag]) && $this->services[$type][$tag] !== $implement) {
            throw new AssertionException(
                sprintf(
                    '%s for tag "%s" already exists with implement type "%s".',
                    $this->typeToString($type),
                    $tag,
                    $this->services[$type][$tag]
                )
            );
        }

        if (!isset($this->services[$type][$tag]) || $implement) {
            $this->services[$type][$tag] = $implement;
        }

        return $this->prefix($type.'.'.$tag);
    }

    /**
     * @param int      $type
     * @param string   $tag
     * @param callable $factory
     */
    public function overrideCollection($type, $tag, callable $factory)
    {
        if (array_key_exists($tag, $this->services[$type])) {
            throw new AssertionException(
                sprintf(
                    '%s for tag "%s" already exists. Try moving the extension that overrides it immediately after "%s".',
                    $this->typeToString($type),
                    $tag,
                    get_class($this)
                )
            );
        }

        $this->overrides[$type][$tag] = $factory($this->getCollection($type, $tag));
    }

    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('resolverFactory'))
            ->setClass('Arachne\ServiceCollections\ResolverFactory');

        $builder->addDefinition($this->prefix('iteratorFactory'))
            ->setClass('Arachne\ServiceCollections\IteratorFactory');

        $builder->addDefinition($this->prefix('iteratorResolverFactory'))
            ->setClass('Arachne\ServiceCollections\IteratorResolverFactory');
    }

    public function beforeCompile()
    {
        foreach ($this->services[self::TYPE_RESOLVER] as $tag => $implement) {
            $this->checkImplementTypes($tag, $implement);

            $this->addService(
                self::TYPE_RESOLVER.'.'.$tag,
                'Closure',
                'Arachne\ServiceCollections\ResolverFactory',
                $this->processResolverServices($tag)
            );
        }

        foreach ($this->services[self::TYPE_ITERATOR] as $tag => $implement) {
            $this->checkImplementTypes($tag, $implement);

            $this->addService(
                self::TYPE_ITERATOR.'.'.$tag,
                'Iterator',
                'Arachne\ServiceCollections\IteratorFactory',
                $this->processIteratorServices($tag)
            );
        }

        foreach ($this->services[self::TYPE_ITERATOR_RESOLVER] as $tag => $implement) {
            $this->checkImplementTypes($tag, $implement);

            $this->addService(
                self::TYPE_ITERATOR_RESOLVER.'.'.$tag,
                'Closure',
                'Arachne\ServiceCollections\IteratorResolverFactory',
                $this->processIteratorResolverServices($tag)
            );
        }
    }

    /**
     * @param string      $tag
     * @param string|null $implement
     */
    private function checkImplementTypes($tag, $implement = null)
    {
        if (!$implement) {
            return;
        }

        $builder = $this->getContainerBuilder();

        foreach ($builder->findByTag($tag) as $name => $attributes) {
            $class = $builder->getDefinition($name)->getClass();

            if ($class !== $implement && !is_subclass_of($class, $implement)) {
                throw new AssertionException(
                    sprintf('Service "%s" is not an instance of "%s".', $name, $implement)
                );
            }
        }
    }

    /**
     * @param string $name
     * @param string $class
     * @param string $factory
     * @param array  $services
     */
    private function addService($name, $class, $factory, array $services)
    {
        $this
            ->getContainerBuilder()
            ->addDefinition($this->prefix($name))
            ->setClass($class)
            ->setFactory(sprintf('@%s::create', $factory), [$services])
            ->setAutowired(false);
    }

    /**
     * @param string $tag
     *
     * @return array
     */
    private function processResolverServices($tag)
    {
        $services = [];
        foreach ($this->getContainerBuilder()->findByTag($tag) as $key => $attributes) {
            $names = (array) (isset($attributes[self::ATTRIBUTE_RESOLVER]) ? $attributes[self::ATTRIBUTE_RESOLVER] : $attributes);

            foreach ($names as $name) {
                if (!is_string($name)) {
                    throw new AssertionException(
                        sprintf('Service "%s" has no resolver name for tag "%s".', $key, $tag)
                    );
                }

                if (isset($services[$name])) {
                    throw new AssertionException(
                        sprintf(
                            'Services "%s" and "%s" both have resolver name "%s" for tag "%s".',
                            $services[$name],
                            $key,
                            $name,
                            $tag
                        )
                    );
                }

                $services[$name] = $key;
            }
        }

        return $services;
    }

    /**
     * @param string $tag
     *
     * @return array
     */
    private function processIteratorServices($tag)
    {
        return array_keys($this->getContainerBuilder()->findByTag($tag));
    }

    /**
     * @param string $tag
     *
     * @return array
     */
    private function processIteratorResolverServices($tag)
    {
        $services = [];
        foreach ($this->getContainerBuilder()->findByTag($tag) as $key => $attributes) {
            $names = (array) (isset($attributes[self::ATTRIBUTE_ITERATOR_RESOLVER]) ? $attributes[self::ATTRIBUTE_ITERATOR_RESOLVER] : $attributes);

            foreach ($names as $name) {
                if (!is_string($name)) {
                    throw new AssertionException(
                        sprintf('Service "%s" has no iterator resolver name for tag "%s".', $key, $tag)
                    );
                }

                $services[$name][] = $key;
            }
        }

        return $services;
    }

    /**
     * @param int $type
     *
     * @return string
     */
    private function typeToString($type)
    {
        switch ($type) {
            case self::TYPE_RESOLVER:
                return 'Resolver';
            case self::TYPE_ITERATOR:
                return 'Iterator';
            case self::TYPE_ITERATOR_RESOLVER:
                return 'Iterator resolver';
        }
    }
}
