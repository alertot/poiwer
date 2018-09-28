<?php

namespace App\Poiwer;

class InternalClass
{
    public $node;
    public $name;
    public $namespace;
    public $baseClasses;
    public $interfaces;
    public $properties;

    public function __construct(
        $node,
        $name,
        $namespace,
        $baseClasses,
        $interfaces,
        $properties
    ) {
        $this->node= $node;
        $this->name = $name;
        $this->namespace = $namespace;
        $this->baseClasses = $baseClasses;
        $this->interfaces = $interfaces;
        $this->properties = $properties;
    }
}

class Search
{
    private $classes;
    private $methods;

    private $classBlacklist = [
        'Traversable',
        'Iterator',
        'IteratorAggregate',
        'Throwable',
        'ArrayAccess',
        'Serializable',
        'Closure',
        'Generator',
        'Countable',
        'OuterIterator',
        'RecursiveIterator',
        'SeekableIterator',
        'Countable',
        'OuterIterator',
        'RecursiveIterator',
        'SeekableIterator',
        'ArrayObject',
    ];

    private $sets = array(
        'array_get' => array(
            'interfaces' => ['ArrayAccess'],
            'baseClasses' => ['ArrayObject'],
            'methods' => ['offsetGet'],
        ),
        'array_set' => array(
            'interfaces' => ['ArrayAccess'],
            'baseClasses' => ['ArrayObject'],
            'methods' => ['offsetSet'],
        ),
        'array_exists' => array(
            'interfaces' => ['ArrayAccess'],
            'baseClasses' => ['ArrayObject'],
            'methods' => ['offsetExists'],
        ),
        'countable' => array(
            'interfaces' => ['Countable'],
            'baseClasses' => ['ArrayObject'],
            'methods' => ['count']
        ),
        'iterator' => array(
            'interfaces' => ['Iterator'],
            'methods' => ['next']
        ),
        'iterator_agg' => array(
            'interfaces' => ['IteratorAggregate'],
            'baseClasses' => ['ArrayObject'],
            'methods' => ['getIterator']
        ),
        'json' => array(
            'interfaces' => ['JsonSerializable'],
            'methods' => ['jsonSerialize']
        ),
        'serializable' => array(
            'interfaces' => ['Serializable'],
            'methods' => ['serialize']
        )
    );

    public function __construct($classes, $methods)
    {
        $this->classes= $classes;
        $this->methods = $methods;
    }

    public function getSet($key)
    {
        return $this->sets[$key] ?? null;
    }

    public function searchSet($key)
    {
        $set = $this->sets[$key] ?? null;
        if (!$set) {
            return null;
        }

        foreach ($set['methods'] as $method) {
            $gadgets = $this->methods[$method] ?? null;

            if (!$gadgets) {
                return null;
            }

            foreach ($gadgets as $gadget) {
                $baseClasses = $gadget->getBaseClasses();

                if (array_key_exists('baseClasses', $set)) {
                    $hasMatch = false; // flag to finish gadget loop after yielding

                    foreach ($baseClasses as $baseClass) {
                        if (in_array($baseClass, $set['baseClasses'])) {
                            yield $gadget;
                            $hasMatch = true;
                            break;
                        }
                    }

                    if ($hasMatch) {
                        continue;
                    }
                }

                $interfaces = $gadget->getInterfaces();
                foreach ($interfaces as $interface) {
                    if (in_array($interface, $set['interfaces'])) {
                        yield $gadget;
                        break;
                    }
                }
            }
        }
    }

    public function searchMethod($key)
    {
        $gadgets = $this->methods[$key] ?? null;
        if (!$gadgets) {
            return [];
        }

        foreach ($gadgets as $gadget) {
            yield $gadget;
        }
    }

    public function searchClassMethod($key, $previousGadget)
    {
        $gadgets = $this->methods[$key] ?? null;
        if (!$gadgets) {
            return null;
        }

        foreach ($gadgets as $gadget) {
            if (
                ($previousGadget->getClassName() === $gadget->getClassName()) &&
                ($previousGadget->getFilePath() === $gadget->getFilePath())
            ) {
                return $gadget;
            }
        }
    }

    public function searchClass($key)
    {
        if ($this->isClassNameAllowed($key)) {
            return $this->classes[$key][0] ?? null;
        }
    }

    public function isClassNameAllowed($className)
    {
        return !in_array($className, $this->classBlacklist);
    }
}
