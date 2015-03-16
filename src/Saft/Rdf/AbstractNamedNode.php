<?php

namespace Saft\Rdf;

abstract class AbstractNamedNode implements NamedNode
{
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getValue();
    }

    /**
     * @see \Saft\Rdf\Node
     */
    public function equals(Node $toCompare)
    {
        // It only compares URIs, everything will be quit with false.
        if (true === $toCompare->isNamed()) {
            return $this->getValue() == $toCompare->getValue();
        }

        return false;
    }

    /**
     * @return boolean
     */
    public function isConcrete()
    {
        return true;
    }

    /**
     * @return boolean
     */
    public function isLiteral()
    {
        return false;
    }

    /**
     * @return boolean
     */
    public function isNamed()
    {
        return true;
    }

    /**
     * @return boolean
     */
    public function isBlank()
    {
        return false;
    }

    /**
     * @return boolean
     */
    public function isReturnable()
    {
        return false;
    }
    
    /**
     * @return boolean
     */
    public function isVariable()
    {
        return false;
    }

    /**
     * @return string
     */
    public function toNQuads()
    {
        return '<' . $this->getValue() . '>';
    }
}
