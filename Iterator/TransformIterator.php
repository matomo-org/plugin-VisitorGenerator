<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\Iterator;

use Iterator;

class TransformIterator implements \OuterIterator
{
    /**
     * @var Iterator
     */
    private $iterator;

    /**
     * @var callable
     */
    private $transform;

    public function __construct($iterator, $transform)
    {
        $this->iterator = $iterator;
        $this->transform = $transform;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        $fn = $this->transform;
        return $fn($this->iterator->current(), $this->iterator->key(), $this->iterator);
    }

    public function next(): void
    {
        $this->iterator->next();
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->iterator->key();
    }

    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
    }

    public function getInnerIterator(): ?Iterator
    {
        return $this->iterator;
    }
}
