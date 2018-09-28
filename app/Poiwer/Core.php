<?php

namespace App\Poiwer;

/**
 * Class in charge of managing property changes in objects
 * and complement them with parent classes properties.
 */

class AttributeProxy
{
    private $searchInstance;
    private $references = array();

    public function __construct($searchInstance)
    {
        $this->searchInstance = $searchInstance;
    }

    private function getKey($gadget)
    {
        return $gadget->ic->name;
    }

    private function getParentProperties($gadget)
    {
        $className = $gadget->getBaseClass();
        if (!$className) {
            return [];
        }

        $class = $this->searchInstance->searchClass($className);
        if ($class) {
            return $class->properties;
        } else {
            return [];
        }
    }

    public function add($gadget)
    {
        // Insert just once
        $key = $this->getKey($gadget);
        if (array_key_exists($key, $this->references)) {
            return;
        }

        $this->references[$key] = [];

        // // Get parent properties
        // foreach ($this->getParentProperties($gadget) as $name => $value) {
        //     $this->references[$key][$name] = array(
        //         'value' => $value, 'modified' => false
        //     );
        // }

        // Override parent properties with gadget properties
        foreach ($gadget->getProperties() as $name => $value) {
            $this->references[$key][$name] = array(
                'value' => $value, 'modified' => false
            );
        }
    }

    public function setProperty($gadget, $name, $value)
    {
        $key = $this->getKey($gadget);
        $this->references[$key][$name]['value'] = $value;
        $this->references[$key][$name]['modified'] = true;
    }

    public function getFullProperties($gadget)
    {
        $key = $this->getKey($gadget);
        return $this->references[$key];
    }
}
