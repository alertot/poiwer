<?php

namespace App\Poiwer;

use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Node\Statement\InterfaceDeclaration;
use Microsoft\PhpParser\Node\Statement\NamespaceDefinition;
use Microsoft\PhpParser\Node\ClassInterfaceClause;
use Microsoft\PhpParser\Node\ClassBaseClause;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\PositionUtilities;

class SourceFileParser
{
    private $filepath;
    private $contents;

    public function __construct($filepath)
    {
        $this->filepath = $filepath;
        $this->contents = file_get_contents($filepath);

        // Create tree
        $parser = new Parser();
        $this->ast_node = $parser->parseSourceFile($this->contents);
        $this->ast_node->parent = null;
        unset($this->ast_node->statementList[0]);
    }

    public function getDescendantNodesByClass(&$node, ...$classes)
    {
        foreach ($node->getDescendantNodes() as $n) {
            foreach ($classes as $class) {
                if ($n instanceof $class) {
                    yield $n;
                }
            }
        }
    }

    public function getNameToken(&$node)
    {
        foreach ($node->getChildTokens() as $token) {
            $tokenName = $token->getTokenKindNameFromValue($token->kind);
            if ($tokenName === 'Name') {
                return $token;
            }
        }
        return null;
    }

    public function getName(&$node)
    {
        $token = $this->getNameToken($node);
        return trim($token->getFullText($this->contents));
    }

    /**
     * Return base classes.
     * Classes in PHP can have only one base class,
     * but interfaces could have many (uses extends).
     */
    public function getBaseClasses(&$node)
    {
        $baseClasses = [];

        if ($node instanceof ClassDeclaration) {
            $classBaseClause = $node->classBaseClause;
            if ($classBaseClause) {
                $name_part = $classBaseClause->baseClass->nameParts[0];
                $baseClasses[] = trim($name_part->getFullText($this->contents));
            }
        } elseif ($node instanceof InterfaceDeclaration) {
            $ibc = $node->interfaceBaseClause;
            if ($ibc) {
                foreach ($ibc->interfaceNameList->getValues() as $value) {
                    $name_part = $value->nameParts[0];
                    $baseClasses[] = trim($name_part->getFullText($this->contents));
                }
            }
        }

        return $baseClasses;
    }

    public function getInterfaces(&$node)
    {
        $interfaceClause = $node->getFirstDescendantNode(ClassInterfaceClause::class);
        if (!$interfaceClause) {
            return [];
        }

        $interfaces = [];
        foreach ($interfaceClause->interfaceNameList->getValues() as $value) {
            $name_part = $value->nameParts[0];
            $interfaces[] = trim($name_part->getFullText($this->contents));
        }

        return $interfaces;
    }

    public function getProperties(&$node)
    {
        $properties = [];
        if ($node instanceof ClassDeclaration) {
            foreach ($node->classMembers->classMemberDeclarations as $member) {
                if ($member instanceof PropertyDeclaration) {
                    $text = $member->getText();

                    if (strpos($text, '=') === false) {
                        // It's only the variable statement, no definition
                        // Delete last ;
                        $name = trim(rtrim($text, ';'));
                        $properties[$name] = null;
                    } else {
                        $parts = explode('=', $text);
                        if (count($parts) === 2) {
                            $name = trim($parts[0]);
                            // Delete last ;
                            $value = trim(rtrim($parts[1], ';'));
                            $properties[$name] = $value;
                        }
                    }
                }
            }
        }

        return $properties;
    }

    public function getClassesAndGadgets()
    {
        // Get namespace name
        $namespaceNode = $this->ast_node->getFirstDescendantNode(
            NamespaceDefinition::class
        );
        $namespaceName = '';
        if ($namespaceNode && !empty($namespaceNode->name)) {
            $namespaceName = trim($namespaceNode->name->getText());
        }

        foreach ($this->getDescendantNodesByClass(
            $this->ast_node,
            ClassDeclaration::class,
            InterfaceDeclaration::class
        ) as $classNode) {
            $className = $this->getName($classNode);

            $ic = new InternalClass(
                $classNode,
                $className,
                $namespaceName,
                $this->getBaseClasses($classNode),
                $this->getInterfaces($classNode),
                $this->getProperties($classNode)
            );
            yield $ic;

            foreach ($this->getDescendantNodesByClass(
                $classNode,
                MethodDeclaration::class
            ) as $methodNode) {
                $methodName = $this->getName($methodNode);

                $contents = $methodNode->getText();
                $contents = trim(preg_replace('/\t/', '  ', $contents));
                $sourceInfo = [
                    'filepath' => $this->filepath,
                    'contents' => $contents,
                    'position' => PositionUtilities::getLineCharacterPositionFromPosition(
                        $methodNode->getStart(),
                        $this->contents
                    )->line + 1
                ];

                $gadget = new Gadget(
                    $methodName,
                    $ic,
                    $sourceInfo
                );

                yield $gadget;
            }
        }
    }
}
