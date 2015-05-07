<?php

namespace Saft\Store;

use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\Statement;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementIterator;
use Saft\Sparql\SparqlUtils;

/**
 * Predefined sparql Store. All Triple methods reroute to the query-method. In the specific sparql-Store those
 * no longer have to be implemented, but only the Query method / SPARQL interpreter itself.
 */
abstract class AbstractSparqlStore implements Store
{
    /**
     * @var NodeFactory
     */
    private $nodeFactory;

    /**
     * @var StatementFactory
     */
    private $statementFactory;

    /**
     * @param NodeFactory $nodeFactory
     * @param StatementFactory $statementFactory
     */
    public function __construct(NodeFactory $nodeFactory, StatementFactory $statementFactory)
    {
        $this->nodeFactory = $nodeFactory;
        $this->statementFactory = $statementFactory;
    }

    /**
     * Adds multiple Statements to (default-) graph.
     *
     * @param  StatementIterator $statements          StatementList instance must contain Statement instances
     *                                                which are 'concret-' and not 'pattern'-statements.
     * @param  Node              $graph      optional Overrides target graph. If set, all statements will
     *                                                be add to that graph, if available.
     * @param  array             $options    optional It contains key-value pairs and should provide additional
     *                                                introductions for the store and/or its adapter(s).
     * @return boolean Returns true, if function performed without errors. In case an error occur, an exception
     *                 will be thrown.
     * @todo implement usage of graph inside the statement(s). create groups for each graph
     */
    public function addStatements(StatementIterator $statements, Node $graph = null, array $options = array())
    {
        $graphUriToUse = null;

        /**
         * Create batches out of given statements to improve statement throughput.
         */
        $counter = 0;
        $batchSize = 100;
        $batchStatements = array();

        foreach ($statements as $statement) {
            // non-concrete Statement instances not allowed
            if (false === $statement->isConcrete()) {
                throw new \Exception('At least one Statement is not concrete');
            }

            // given $graph forces usage of it and not the graph from the statement instance
            if (null !== $graph) {
                $graphUriToUse = $graph->getUri();
                // reuse $graph instance later on.

            // use graphUri from statement
            } elseif (null !== $statement->getGraph()) {
                $graph = $statement->getGraph();
                $graphUriToUse = $graph->getUri();

            // no graph instance was found
            } else {
                throw new \Exception('Graph was not given, neither as parameter nor in statement.');
            }

            if (false === isset($batchStatements[$graphUriToUse])) {
                $batchStatements[$graphUriToUse] = new ArrayStatementIteratorImpl(array());
            }
            $batchStatements[$graphUriToUse]->append($statement);

            // after batch is full, execute collected statements all at once
            if (0 === $counter % $batchSize) {
                /**
                 * $batchStatements is an array with graphUri('s) as key(s) and ArrayStatementIteratorImpl
                 * instances as value. Each entry is related to a certain graph and contains a bunch of
                 * statement instances.
                 */
                foreach ($batchStatements as $graphUriToUse => $batch) {
                    foreach ($batch as $batchEntries) {
                        $content = $this->sparqlFormat(
                            new ArrayStatementIteratorImpl(array($batchEntries)),
                            $graph
                        );

                        $this->query('INSERT DATA {'. $content .'}', $options);
                    }
                }

                // re-init variables
                $batchStatements = array();
            }
        }
    }

    /**
     * Removes all statements from a (default-) graph which match with given statement.
     *
     * @param  Statement $statement          It can be either a concrete or pattern-statement.
     * @param  Node      $graph     optional Overrides target graph. If set, all statements will be delete in
     *                                       that graph.
     * @param  array     $options   optional It contains key-value pairs and should provide additional
     *                                       introductions for the store and/or its adapter(s).
     * @return boolean Returns true, if function performed without errors. In case an error occur, an exception
     *                 will be thrown.
     */
    public function deleteMatchingStatements(Statement $statement, Node $graph = null, array $options = array())
    {
        // given $graph forces usage of it and not the graph from the statement instance
        if (null !== $graph) {
            $graphUriToUse = $graph->getUri();

        // use graphUri from statement
        } elseif (null !== $statement->getGraph()) {
            $graph = $statement->getGraph();
            $graphUriToUse = $graph->getUri();

        } else {
            throw new \Exception('Graph was not given, neither as parameter nor in statement.');
        }

        $statementIterator = new ArrayStatementIteratorImpl(array($statement));

        $query = 'DELETE DATA { '. $this->sparqlFormat($statementIterator, $graph) .'}';
        return $this->query($query, $options);
    }

    /**
     * Returns array with graphUri's which are available.
     *
     * @return array Array which contains graph URI's as values and keys.
     */
    public function getAvailableGraphs()
    {
        $result = $this->query('SELECT DISTINCT ?g WHERE { GRAPH ?g {?s ?p ?o.} }');

        $graphs = array();

        foreach ($result as $entry) {
            $graphs[$entry['g']] = $entry['g'];
        }

        return $graphs;
    }

    /**
     * It gets all statements of a given graph which match the following conditions:
     * - statement's subject is either equal to the subject of the same statement of the graph or it is null.
     * - statement's predicate is either equal to the predicate of the same statement of the graph or it is null.
     * - statement's object is either equal to the object of a statement of the graph or it is null.
     *
     * @param  Statement $statement          It can be either a concrete or pattern-statement.
     * @param  Node      $graph     optional Overrides target graph. If set, you will get all
     *                                       matching statements of that graph.
     * @param  array     $options   optional It contains key-value pairs and should provide additional
     *                                       introductions for the store and/or its adapter(s).
     * @return StatementIterator It contains Statement instances  of all matching
     *                           statements of the given graph.
     * @todo FILTER select
     */
    public function getMatchingStatements(Statement $statement, Node $graph = null, array $options = array())
    {
        // TODO migrate code to new interface
        $graphUri = null;
        if ($graph !== null) {
            $graphUri = $graph->getUri();
        }

        $query = "";
        if ($graphUri !== null) {
            $query = "FROM <" . $graphUri . ">" . PHP_EOL;
        } elseif ($statement->isQuad() && $statement->getGraph()->isConcrete()) {
            $query = "FROM <" . $statement->getGraph()->getUri() . ">" . PHP_EOL;
        }

        $query.= "SELECT * WHERE { ";
        $query.= SparqlUtils::getNodeInSparqlFormat($statement->getSubject(), "s") . " ";
        $query.= SparqlUtils::getNodeInSparqlFormat($statement->getPredicate(), "p") . " ";
        $query.= SparqlUtils::getNodeInSparqlFormat($statement->getObject(), "o") . " ";
        $query.= " }" . PHP_EOL;

        return $this->query($query, $options);
    }

    /**
     * Returns true or false depending on whether or not the statements pattern
     * has any matches in the given graph.
     *
     * @param  Statement $statement          It can be either a concrete or pattern-statement.
     * @param  Node      $graph     optional Overrides target graph.
     * @param  array     $options   optional It contains key-value pairs and should provide additional
     *                                       introductions for the store and/or its adapter(s).
     * @return boolean Returns true if at least one match was found, false otherwise.
     */
    public function hasMatchingStatement(Statement $statement, Node $graph = null, array $options = array())
    {
        // TODO migrate code to new interface
        $graphUri = null;
        if ($graph !== null) {
            $graphUri = $graph->getUri();
        }

        $statementIterator = new ArrayStatementIteratorImpl(array($statement));
        $result = $this->query('ASK { '. $this->sparqlFormat($statementIterator, $graphUri) .'}', $options);

        if (true === is_object($result)) {
            return $result->getResultObject();
        } else {
            return $result;
        }
    }

    /**
     * Returns the Statement-Data in sparql-Format.
     *
     * @param StatementIterator $statements   List of statements to format as SPARQL string.
     * @param string            $graphUri     Use if each statement is a triple and to use another graph as
     *                                        the default.
     * @return string, part of query
     */
    protected function sparqlFormat(StatementIterator $statements, Node $graph = null)
    {
        return SparqlUtils::statementIteratorToSparqlFormat($statements, $graph);
    }
}
