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

    /**
     * @param string $version Default '1.0'
     * @param string $encoding Default 'UTF-8'
     */
    public function __construct($version = '1.0', $encoding = 'UTF-8')
    {
        $this->version = $version;
        $this->encoding = $encoding;
    }

    /**
     * @param string $name The name of element
     * @param array $args An array with:
     *                      - (string) value of element
     *                      - (string) node element to add this element as child.
     *                      - (array) attributes of element
     *
     * @return XmlBuilder
     */
    public function __call($name, $args)
    {
        $value = array_shift($args);
        $parentNode = array_shift($args);
        $attrs = array_shift($args) ?: array();

        if (null === $this->root) {
            $this->createRootNode($name, $attrs);

            return $this;
        }

        $this->createNode($name, $value, $attrs, $parentNode);

        return $this;
    }

    private function createNode($name, $value, array $attrs = array(), $parentNode = null)
    {
        if ($parentNode) {
            $node = $this->xpath('//' . $parentNode)->addChild($name, $value);
        } else {
            $node = $this->root->addChild($name, $value);
        }
        $this->addAttributes($node, $attrs);
    }

    private function createRootNode($tag, array $attrs = array())
    {
        $root = '<?xml version="%s" encoding="%s"?><%s></%s>';
        $string = sprintf($root, $this->version, $this->encoding, $tag, $tag);
        $this->root = new SimpleXmlElement($string);
        $this->addAttributes($this->root, $attrs);
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
