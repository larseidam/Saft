<?php

namespace Saft\Rdf\Test;

use Saft\Rdf\AnyPatternImpl;
use Saft\Rdf\BlankNodeImpl;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementImpl;
use Saft\Test\TestCase;

class RdfHelpersTest extends TestCase
{
    protected $rdfHelpers;

    public function setUp()
    {
        parent::setUp();

        $this->fixture = new RdfHelpers();
    }

    /*
     * Tests for encodeStringLitralForNQuads
     */

    public function testEncodeStringLitralForNQuads()
    {
        $this->assertEquals('\\\\', $this->fixture->encodeStringLitralForNQuads('\\'));
        $this->assertEquals('\t', $this->fixture->encodeStringLitralForNQuads("\t"));
        $this->assertEquals('\r', $this->fixture->encodeStringLitralForNQuads("\r"));
        $this->assertEquals('\n', $this->fixture->encodeStringLitralForNQuads("\n"));
        $this->assertEquals('\"', $this->fixture->encodeStringLitralForNQuads('"'));
    }

    /*
     * Tests for getNodeInSparqlFormat
     */

    public function testGetNodeInSparqlFormat()
    {
        $this->assertTrue(21 == strlen($this->fixture->getNodeInSparqlFormat(new AnyPatternImpl(), null)));

        $this->assertEquals(
            '<http://localhost/Saft/TestGraph/>',
            $this->fixture->getNodeInSparqlFormat($this->testGraph, null)
        );

        $literal = new LiteralImpl($this->fixture, 'foobar');
        $this->assertEquals(
            '"foobar"^^<http://www.w3.org/2001/XMLSchema#string>',
            $this->fixture->getNodeInSparqlFormat($literal, null)
        );
    }

    /*
     * Tests for getQueryType
     */

    public function testGetQueryTypeAsk()
    {
        $query = 'PREFIX foaf:    <http://xmlns.com/foaf/0.1/>
            PREFIX vcard:   <http://www.w3.org/2001/vcard-rdf/3.0#>
            ASK
            WHERE       { ?x foaf:name ?name }';

        $this->assertEquals('askQuery', $this->fixture->getQueryType($query));
    }

    public function testGetQueryTypeClearGraph()
    {
        $query = 'CLEAR GRAPH <';

        $this->assertEquals('graphQuery', $this->fixture->getQueryType($query));
    }

    public function testGetQueryTypeConstruct()
    {
        $query = 'PREFIX foaf:    <http://xmlns.com/foaf/0.1/>
            PREFIX vcard:   <http://www.w3.org/2001/vcard-rdf/3.0#>
            CONSTRUCT   { <http://example.org/person#Alice> vcard:FN ?name }
            WHERE       { ?x foaf:name ?name }';

        $this->assertEquals('constructQuery', $this->fixture->getQueryType($query));
    }

    public function testGetQueryTypeDescribe()
    {
        $query = 'PREFIX foaf:   <http://xmlns.com/foaf/0.1/>
            DESCRIBE ?x ?y <http://example.org/>
            WHERE    {?x foaf:knows ?y}';

        $this->assertEquals('describeQuery', $this->fixture->getQueryType($query));
    }

    public function testGetQueryTypeDropGraph()
    {
        $query = 'DROP Graph <';

        $this->assertEquals('graphQuery', $this->fixture->getQueryType($query));
    }

    public function testGetQueryTypeInsertInto()
    {
        $query = 'INSERT INTO <';

        $this->assertEquals('updateQuery', $this->fixture->getQueryType($query));
    }

    public function testGetQueryTypeSelect()
    {
        $query = 'PREFIX foaf: <http://xmlns.com/foaf/0.1/>
            PREFIX vcard:<http://www.w3.org/2001/vcard-rdf/3.0#>
            Select ?s ?p ?o
            WHERE { ?x foaf:name ?name }';

        $this->assertEquals('selectQuery', $this->fixture->getQueryType($query));
    }

    /*
     * Tests for guessFormat
     */

    public function testGuessFormat()
    {
        // n-triples
        $this->assertEquals('n-triples', $this->fixture->guessFormat('<foo><bar>'));
        $this->assertEquals('n-triples', $this->fixture->guessFormat('<foo>'));

        // rdf xml
        $this->assertEquals('rdf-xml', $this->fixture->guessFormat('<rdf:aa'));

        // turtle
        $this->assertEquals('turtle', $this->fixture->guessFormat('@prefix foo:<http://bar>'));

        // invalid strings
        $this->assertEquals(null, $this->fixture->guessFormat('<foo'));
        $this->assertEquals(null, $this->fixture->guessFormat('foo://>'));
    }

    public function testGuessFormatInvalidParameter()
    {
        $this->setExpectedException('\Exception');

        $this->fixture->guessFormat(1);
    }

    /*
     * Tests for simpleCheckBlankNodeId
     */

    public function testSimpleCheckBlankNodeId()
    {
        $this->assertFalse($this->fixture->simpleCheckBlankNodeId('_a'));
        $this->assertFalse($this->fixture->simpleCheckBlankNodeId(':aaa'));

        $this->assertTrue($this->fixture->simpleCheckBlankNodeId('_:aaa'));
        $this->assertTrue($this->fixture->simpleCheckBlankNodeId('_:aaa/_aa'));
    }

    /*
     * Tests for simpleCheckURI
     */

    public function testSimpleCheckURI()
    {
        $this->assertFalse($this->fixture->simpleCheckURI(''));
        $this->assertFalse($this->fixture->simpleCheckURI('http//foobar/'));

        $this->assertTrue($this->fixture->simpleCheckURI('http:foobar/'));
        $this->assertTrue($this->fixture->simpleCheckURI('http://foobar/'));
        $this->assertTrue($this->fixture->simpleCheckURI('http://foobar:42/'));
        $this->assertTrue($this->fixture->simpleCheckURI('http://foo:bar@foobar/'));
    }

    /*
     * Tests for statementIteratorToSparqlFormat
     */

    public function testStatementIteratorToSparqlFormatGraphGivenWithTripleAndQuad()
    {
        $triple = new StatementImpl(
            new NamedNodeImpl($this->fixture, 'http://saft/test/s1'),
            new NamedNodeImpl($this->fixture, 'http://saft/test/p1'),
            new LiteralImpl($this->fixture, "42")
        );

        $quad = new StatementImpl(
            new NamedNodeImpl($this->fixture, 'http://saft/test/s2'),
            new NamedNodeImpl($this->fixture, 'http://saft/test/p2'),
            new LiteralImpl($this->fixture, "43"),
            new NamedNodeImpl($this->fixture, 'http://some/other/graph/2')
        );

        $this->assertEquals(
            'Graph <http://localhost/Saft/TestGraph/> {<http://saft/test/s1> <http://saft/test/p1> '.
            '"42"^^<http://www.w3.org/2001/XMLSchema#string> . } '.
            'Graph <http://localhost/Saft/TestGraph/> {<http://saft/test/s2> <http://saft/test/p2> '.
            '"43"^^<http://www.w3.org/2001/XMLSchema#string> . }',
            trim(
                $this->fixture->statementIteratorToSparqlFormat(array($triple, $quad), $this->testGraph)
            )
        );
    }

    public function testStatementIteratorToSparqlFormatTripleAndQuad()
    {
        $triple = new StatementImpl(
            new NamedNodeImpl($this->fixture, 'http://saft/test/s1'),
            new NamedNodeImpl($this->fixture, 'http://saft/test/p1'),
            new LiteralImpl($this->fixture, '42')
        );

        $quad = new StatementImpl(
            new NamedNodeImpl($this->fixture, 'http://saft/test/s1'),
            new NamedNodeImpl($this->fixture, 'http://saft/test/p1'),
            new LiteralImpl($this->fixture, '42'),
            $this->testGraph
        );

        $this->assertEquals(
            '<http://saft/test/s1> <http://saft/test/p1> "42"^^<http://www.w3.org/2001/XMLSchema#string> .  '.
            'Graph <http://localhost/Saft/TestGraph/> {<http://saft/test/s1> <http://saft/test/p1> '.
            '"42"^^<http://www.w3.org/2001/XMLSchema#string> . }',
            trim($this->fixture->statementIteratorToSparqlFormat(array($triple, $quad)))
        );
    }
}
