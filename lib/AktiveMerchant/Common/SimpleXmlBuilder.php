<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Common;

use SimpleXMLElement;

/**
 * XmlBuilder provides a fluent way to create xml strings.
 *
 * @since Class available since Release 1.1.0
 * @package Aktive-Merchant
 * @author Andreas Kollaros <andreas@larium.net>
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class SimpleXmlBuilder
{
    private $root;

    private $version;

    private $encoding;

    private $namespace;

    private $nodes;

    /**
     * @param string $version Default '1.0'
     * @param string $encoding Default 'UTF-8'
     */
    public function __construct($version = '1.0', $encoding = 'UTF-8', $namespace = null)
    {
        $this->version = $version;
        $this->encoding = $encoding;
        $this->namespace = $namespace;
    }

    /**
     * @param string $name The name of element
     * @param array $args An array with:
     *                      - (string) value of element
     *                      - (string) parent node element to add this element as child.
     *                      - (array) attributes of element
     *
     * @return XmlBuilder
     */
    public function __call($name, $args)
    {
        $value = array_shift($args);
        $parentNode = array_shift($args);
        $attrs = array_shift($args) ?: array();
        $namespace = array_shift($args) ?: null;

        if (null === $this->root) {
            $this->createRootNode($name, $attrs);

            return $this;
        }

        $this->createNode($name, $value, $attrs, $parentNode, $namespace);

        return $this;
    }

    public function registerXPathNamespace($prefix, $ns)
    {
        $this->root->registerXPathNamespace($prefix, $ns);
    }

    private function createNode($name, $value, array $attrs = array(), $parentNode = null, $namespace = null)
    {
        if ($parentNode) {
            #$node = $this->xpath('//' . $parentNode);
            $node = $this->nodes[$parentNode];
            $node = $node->addChild($name, $value, $namespace);
        } else {
            $node = $this->root->addChild($name, $value, $namespace);
        }
        $this->addAttributes($node, $attrs);
        $this->nodes[$name] = $node;
    }

    private function createRootNode($tag, array $attrs = array())
    {
        $attr = "";
        if (!empty($attrs)) {
            $attr = $this->serializeAttributes($attrs);
        }
        $root = '<?xml version="%s" encoding="%s"?><%s%s></%s>';
        $string = sprintf($root, $this->version, $this->encoding, $tag, $attr, $tag);
        $this->root = new SimpleXmlElement($string);
        $this->nodes[$tag] = $this->root;
    }

    private function serializeAttributes($attrs)
    {
        $string = "";
        foreach ($attrs as $name => $value) {
            $string .= ' '.$name.'="'.$value.'"';
        }

        return $string;
    }

    private function addAttributes($node, array $attrs)
    {
        foreach ($attrs as $name => $value) {
            $node->addAttribute($name, $value);
        }
    }

    private function xpath($path)
    {
        $results = $this->root->xpath($path);

        return reset($results);
    }

    public function __toString()
    {
        return $this->root->asXML();
    }
}
