<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Common;

use Closure;
use XMLWriter;

/**
 * XmlBuilder provides a fluent way to create xml strings.
 *
 * @since Class available since Release 1.1.0
 * @package Aktive-Merchant
 * @author Andreas Kollaros <andreas@larium.net>
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class XmlBuilder
{
    protected $writer;

    public function __construct()
    {
        $this->writer = new XMLWriter();
        $this->writer->openMemory();
    }

    public function instruct($version, $encoding = null, $indent = true)
    {
        $this->writer->startDocument($version, $encoding);
        if ($indent) {
            $this->writer->setIndent(true);
        }
    }

    public function docType($qualifiedName, $publicId = null, $systemId = null)
    {
        $this->writer->startDTD($qualifiedName, $publicId, $systemId);
        $this->writer->endDTD();
    }

    public function build()
    {
        $args = func_get_args();

        $args = reset($args);
        $name = array_shift($args);
        $block_or_string = array_shift($args);
        $attribute = array_shift($args);

        if ($block_or_string instanceof Closure) {
            $this->writer->startElement($name);
            if ($attribute) {
                foreach ($attribute as $key => $value) {
                    $this->writer->startAttribute($key);
                        $this->writer->text($value);
                    $this->writer->endAttribute();
                }
            }
            $block_or_string($this);
            $this->writer->endElement();
        } else if (is_string($block_or_string)
            || is_numeric($block_or_string)
            || is_null($block_or_string)
        ) {
            if ($attribute) {
                $this->writer->startElement($name);
                foreach ($attribute as $key => $value) {
                    $this->writer->startAttribute($key);
                    $this->writer->text($value);
                    $this->writer->endAttribute();
                }
                $this->writer->text($block_or_string);
                $this->writer->endElement();
            } else {
                $this->writer->writeElement($name, $block_or_string);
            }
        }

        return $this;
    }

    public function __call($name, $args)
    {
        $args = array_merge([$name], $args);

        return $this->build($args);
    }

    public function __toString()
    {
        $this->writer->endDocument();

        return $this->writer->outputMemory();
    }
}
