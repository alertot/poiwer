<?php

namespace App\Poiwer;

class ChainWriter
{
    private $code;

    public function generateCode($chain, $ap)
    {
        $code = "<?php\n\n";
        $needSpecificNS = false;
        $namespace = '';

        // Check if the file needs a global namespace or specific per class
        foreach ($chain as $gadget) {
            if (!$namespace) {
                $namespace = $gadget->getNamespace();
            }
            if ($gadget->getNamespace() !== $namespace) {
                $needSpecificNS = true;
                break;
            }
        }

        if (!$needSpecificNS) {
            $code .= "namespace $namespace;\n";
        }

        foreach ($chain as $gadget) {
            $attributeLines = [];
            $attributesInConstructor = [];
            $constructorLines = [];

            foreach ($ap->getFullProperties($gadget) as $name => $dict) {
                // Don't add unmodified fields
                if ($dict['modified'] === false) {
                    continue;
                }

                $value = $dict['value'];
                $attributeLines[] = "$name = $value;";

                if (strpos($value, 'injection') !== false) {
                    preg_match('/(?:\$)(.*)/', $name, $match);
                    $attributeName = $match[1];

                    $attributesInConstructor[] = '$' . $attributeName;
                    $constructorLines[] = '$this->' . $attributeName . ' = $' . $attributeName . ";\n";
                }
            }

            // Build code
            $classTemplate = "
class %s {
    %s

    public function __construct(%s) {
        %s
    }
}
";
            $className = $gadget->getClassName($needSpecificNS);
            $parent = $gadget->getBaseClass();
            if ($parent) {
                $className .= " extends $parent";
            }

            $classDefinition = sprintf(
                $classTemplate,
                $className,
                implode("\n\t", $attributeLines),
                implode(', ', $attributesInConstructor),
                implode('', $constructorLines)
            );

            $code .= $classDefinition;
        }

        $this->code = $code;
    }

    public function write($filepath)
    {
        file_put_contents($filepath, $this->code);
    }
}
