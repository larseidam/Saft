<?php

namespace Saft\Backend\Redland\Tests\Rdf;

use Saft\Rdf\Test\NodeFactoryAbstractTest;
use Saft\Backend\Redland\Rdf\NodeFactory;

class NodeFactoryTest extends NodeFactoryAbstractTest
{
    /**
     * An abstract method which returns new instances of NodeFactory
     */
    public function getFixture()
    {
        return new NodeFactory();
    }

    /**
     *
     */
    public function setUp()
    {
        if (false === extension_loaded('redland')) {
            $this->markTestSkipped('Extension redland is not loaded.');
        }

        parent::setUp();
    }
}