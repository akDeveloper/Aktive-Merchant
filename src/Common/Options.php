<?php

declare(strict_types=1);

namespace AktiveMerchant\Common;

use AktiveMerchant\Billing\Exception;

/**
 * Options class.
 *
 * Provides an easy way of accessing options with arrays.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class Options implements \ArrayAccess, \Iterator
{

    /**
     * The array of option values.
     *
     * @var array
     * @access private
     */
    private $options = array();

    public function __construct(array $options = array())
    {
        $this->options = $options;
    }

    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * Returns an array from options.
     *
     * @access public
     * @return array
     */
    public function getArrayCopy()
    {
        return $this->options;
    }

    /* -(  Iterator  )------------------------------------------------------ */

    public function rewind(): void
    {
        reset($this->options);
    }

    public function current(): mixed
    {
        return current($this->options);
    }

    public function key(): mixed
    {
        return key($this->options);
    }

    public function next(): void
    {
        next($this->options);
    }

    public function valid(): bool
    {
        return current($this->options) !== false;
    }

    /* -(  ArrayAccess  )--------------------------------------------------- */

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->options[] = $value;
        } else {
            $this->options[$offset] = $value;
        }
    }

    /**
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->options[$offset]);
    }

    public function offsetUnset($offset):void
    {
        unset($this->options[$offset]);
    }

    /**
     * @return mixed|null
     */
    public function offsetGet($offset): mixed
    {
        $value = isset($this->options[$offset])
            ? $this->options[$offset]
            : null;

        if (is_array($value)) {
            return new self($value);
        }

        return $value;
    }

    /**
     * Checks if keys in $required parameter exist in the given $options array.
     *
     * @throws \InvalidArgumentException If a required parameter is missing
     *
     * @param string comma seperated parameters. Represent keys of $options array
     * @param array  the key/value hash of options to compare with
     *
     * @return boolean
     */
    public static function required($required, $options = array())
    {
        $required = explode(',', $required);

        foreach ($required as $r) {
            if (is_array($options)) {
                $exists = array_key_exists(trim($r), $options);
            } elseif ($options instanceof \ArrayAccess) {
                $exists = $options->offsetExists(trim($r));
            }

            if (!isset($exists) || false === $exists) {
                throw new \InvalidArgumentException($r . " parameter is required!");
            }
        }

        return true;
    }
}
