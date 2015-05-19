<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Common;

use Closure;
use XMLWriter;

class XmlBuilder
{
    protected $writer;

    public function __construct()
    {
        $this->writer = new XMLWriter();
        $this->writer->openMemory();
    }

    public function instruct($version, $encoding)
    {
        $this->writer->startDocument($version, $encoding);
        $this->writer->setIndent(true);
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
        } else if (is_string($block_or_string) || is_numeric($block_or_string)) {
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
