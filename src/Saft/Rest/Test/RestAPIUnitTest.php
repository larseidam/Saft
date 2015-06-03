<?php

namespace Saft\Rest\Test;

use Saft\TestCase;
use Saft\Rest\RestApi;

class RestAPIUnitTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = $this->getMockForAbstractClass('\Saft\Store\AbstractSparqlStore');

        // Override query method: it will always return the given query.
        $this->fixture->method('query')->will($this->returnArgument(0));

        $_SERVER['HTTP_ORIGIN'] = 'servername';
        $_POST['request'] = 'store/statements';
    }

    public function getTestStatement()
    {
        $statement = array('http://saft/test/s1',
            'http://saft/test/p1',
            'http://saft/test/o1',
            'http://saft/test/g1'
        );
        return $statement;
    }

    public function getTestStatements()
    {
        $statement1 = array('http://saft/test/s1',
            'http://saft/test/p1',
            'http://saft/test/o1',
            'http://saft/test/g1'
        );
        $statement2 = array('http://saft/test/s2',
            'http://saft/test/p2',
            'http://saft/test/o2',
            'http://saft/test/g2'
        );
        $statements = array($statement1, $statement2);
        return $statements;
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteStatements()
    {
        $_POST['statementsarray'] = $this->getTestStatement();
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->callRestApi(
            'DELETE DATA { Graph <http://saft/test/g1> {'.
            '<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1> . '.
            '} }'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetMatchingStatements()
    {
        $_POST['statementsarray'] = $this->getTestStatement();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->callRestApi(
            'FROM <http://saft/test/g1> ' .
            'SELECT * WHERE {'.
            '<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1>'.
            '}'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testAddStatements()
    {
        $_POST['statementsarray'] = $this->getTestStatements();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->callRestApi(
            'INSERT DATA { Graph <http://saft/test/g1> {'.
            '<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1> . }'.
            ' Graph <http://saft/test/g2> {<http://saft/test/s2> <http://saft/test/p2> <http://saft/test/o2> . } }'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testPassGraphUri()
    {
        $_POST['statementsarray'] = array($this->getTestStatement());
        //use given graphUri
        $_POST['graphUri'] = 'http://saft/test/g2';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->callRestApi(
            'INSERT DATA { Graph <http://saft/test/g2> {'.
            '<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1>. '.
            '} }'
        );

        /**
         * use bad graphUri
         */
        $_POST['graphUri'] = 'foo';
        try {
            $API = new RestApi(
                $_POST['request'],
                $_SERVER['HTTP_ORIGIN'],
                $this->fixture
            );
            $this->setExpectedException('\Exception');
            $query = $API->processAPI();
        } catch (Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }

        $this->markTestIncomplete("I think the following should not be valid");
        /**
         * use graphPattern
         */
        $_POST['graphUri'] = '?graph';
        $this->callRestApi(
            'INSERT DATA { Graph ?graph {'.
            '<http://saft/test/s1> <http://saft/test/p1> <http://saft/test/o1>. '.
            '} }'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testStatementPattern()
    {
        $this->markTestSkipped("We need variable for this");
        //object is a varialbe
        $statement = array('http://saft/test/s1',
            'http://saft/test/p1',
            '?ob',
        );

        $_POST['statementsarray'] = $statement;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->callRestApi(
            'SELECT * WHERE { <http://saft/test/s1> <http://saft/test/p1> ?ob . }'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetGraphs()
    {
        $_POST['request'] = 'store/graph';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        //@TODO
        //$this->callRestApi('');
    }

    /**
     * Call rest-Api and check if return query is quivalent to
     * given $value.
     * @param  string $value
     */
    protected function callRestApi($value)
    {
        $API = new RestApi($_POST['request'], $_SERVER['HTTP_ORIGIN'], $this->fixture);
        $query = $API->processAPI();
        $this->assertEqualsSparql($query, $value);
    }
}