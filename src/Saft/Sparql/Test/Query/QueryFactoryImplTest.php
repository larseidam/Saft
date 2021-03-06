<?php

namespace Saft\Sparql\Test\Query;

use Saft\Rdf\RdfHelpers;
use Saft\Sparql\Query\QueryFactoryImpl;

class QueryFactoryImplTest extends QueryFactoryAbstractTest
{
    /**
     * Returns subject to test.
     *
     * @return QueryFactory
     */
    public function newInstance()
    {
        return new QueryFactoryImpl(new RdfHelpers());
    }
}
