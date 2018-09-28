<?php

namespace App\Poiwer;

use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Node\ClassInterfaceClause;
use Microsoft\PhpParser\Node\ClassBaseClause;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\PositionUtilities;

class Gadget
{
    public $name;
    public $ic;
    public $sourceInfo;

    public function __construct($name, $ic, $sourceInfo)
    {
        $this->name = $name;
        $this->ic = $ic;
        $this->sourceInfo = $sourceInfo;
    }

    public function getClassName($namespace = false)
    {
        $s = '';
        if ($namespace && !empty($this->ic->namespace)) {
            $s = $this->ic->namespace . '\\';
        }

        return $s . $this->ic->name;
    }

    public function getNamespace()
    {
        return $this->ic->namespace;
    }

    public function getBaseClasses()
    {
        return $this->ic->baseClasses;
    }

    public function getBaseClass()
    {
        return $this->ic->baseClasses[0] ?? null;
    }

    public function getInterfaces()
    {
        return $this->ic->interfaces;
    }

    public function getProperties()
    {
        return $this->ic->properties;
    }

    public function getFilePath()
    {
        return $this->sourceInfo['filepath'];
    }

    public function getLineNumber()
    {
        return $this->sourceInfo['position'];
    }

    public function getContents()
    {
        return $this->sourceInfo['contents'];
    }

    public function getClassTree($searchInstance)
    {
        $tree = [];
        $node = $this->ic;

        while ($node) {
            $classDescription = $node->name;
            $baseClasses = $node->baseClasses;
            $interfaces = $node->interfaces;

            if ($baseClasses) {
                $classDescription .= " extends " . implode(', ', $baseClasses);
            }
            if ($interfaces) {
                $classDescription .= " implements " . implode(', ', $interfaces);
            }

            array_push($tree, $classDescription);

            if ($baseClasses) {
                // Base classes for interfaces are interfaces
                $c = $baseClasses[0];
            } elseif (count($interfaces)) {
                $c = $interfaces[0];
            } else {
                break;
            }

            if ($searchInstance->isClassNameAllowed($c)) {
                $node = $searchInstance->searchClass($c);
            } else {
                $node = null;
            }
        }

        return $tree;
    }
}
