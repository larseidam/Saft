<?php

namespace Saft\Addition\EasyRdf\Data;

use Saft\Data\Parser;
use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementIteratorFactory;

class ParserEasyRdf implements Parser
{
    /**
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var RdfHelpers
     */
    protected $rdfHelpers;

    /**
     * @var string
     */
    protected $serialization;

    /**
     * @var array
     */
    protected $serializationMap;

    /**
     * @var StatementFactory
     */
    protected $statementFactory;

    /**
     * Constructor.
     *
     * @param NodeFactory $nodeFactory
     * @param StatementFactory $statementFactory
     * @param StatementIteratorFactory $statementIteratorFactory
     * @param RdfHelpers $rdfHelpers
     * @param string $serialization
     * @throws \Exception if serialization is unknown.
     */
    public function __construct(
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        StatementIteratorFactory $statementIteratorFactory,
        RdfHelpers $rdfHelpers,
        $serialization
    ) {
        $this->RdfHelpers = $rdfHelpers;

        $this->nodeFactory = $nodeFactory;
        $this->statementFactory = $statementFactory;
        $this->statementIteratorFactory = $statementIteratorFactory;

        $this->serializationMap = array(
            'n-triples' => 'ntriples',
            'rdf-json' => 'json',
            'rdf-xml' => 'rdfxml',
            'rdfa' => 'rdfa',
            'turtle' => 'turtle',
        );

        $this->serialization = $this->serializationMap[$serialization];

        if (false == isset($this->serializationMap[$serialization])) {
            throw new \Exception(
                'Unknown serialization format given: '. $serialization .'. Supported are only '.
                implode(', ', array_keys($this->serializationMap))
            );
        }
    }

    /**
     * Returns an array of prefixes which where found during the last parsing.
     *
     * @return array An associative array with a prefix mapping of the prefixes parsed so far. The key
     *               will be the prefix, while the values contains the according namespace URI.
     */
    public function getCurrentPrefixList()
    {
        // TODO implement a way to get a list of all namespaces used in the last parsed datastring/file.
        return array();
    }

    /**
     * Parses a given string and returns an iterator containing Statement instances representing the read data.
     *
     * @param  string $inputString Data string containing RDF serialized data.
     * @param  string $baseUri     The base URI of the parsed content. If this URI is null the inputStreams URL
     *                             is taken as base URI.
     * @return StatementIterator StatementIterator instaince containing all the Statements parsed by the
     *                           parser to far
     * @throws \Exception if the base URI $baseUri is no valid URI.
     */
    public function parseStringToIterator($inputString, $baseUri = null)
    {
        // check $baseUri
        if (null !== $baseUri && false == $this->RdfHelpers->simpleCheckURI($baseUri)) {
            throw new \Exception('Parameter $baseUri is not a valid URI.');
        }

        $graph = new \EasyRdf_Graph();
        $graph->parse($inputString, $this->serialization, $baseUri);

        // transform parsed data to PHP array
        return $this->rdfPhpToStatementIterator($graph->toRdfPhp());
    }

    /**
     * Parses a given stream and returns an iterator containing Statement instances representing the
     * previously read data. The stream parses the data not as a whole but in chunks.
     *
     * @param  string $inputStream Filename of the stream to parse which contains RDF serialized data.
     * @param  string $baseUri     The base URI of the parsed content. If this URI is null the inputStreams URL
     *                             is taken as base URI.
     * @return StatementIterator A StatementIterator containing all the Statements parsed by the parser to far.
     * @throws \Exception if the base URI $baseUri is no valid URI.
     */
    public function parseStreamToIterator($inputStream, $baseUri = null)
    {
        // check $baseUri
        if (null !== $baseUri && false == $this->RdfHelpers->simpleCheckURI($baseUri)) {
            throw new \Exception('Parameter $baseUri is not a valid URI.');
        }

        $graph = new \EasyRdf_Graph();
        $graph->parseFile($inputStream, $this->serialization);

        // transform parsed data to PHP array
        return $this->rdfPhpToStatementIterator($graph->toRdfPhp());
    }

    /**
     * Transforms a statement array given by EasyRdf to a Saft StatementIterator instance.
     *
     * @param array $rdfPhp RDF data structured as array. It structure looks like:
     *                      array(
     *                         $subject => array(
     *                             $predicate => array (
     *                                 // object information
     *                                 'datatype' => ...,
     *                                 'lang' => ...,
     *                                 'value' => ...
     *                             )
     *                          )
     *                      )
     * @return StatementIterator
     * @throws \Exception if a non-URI or non-BlankNode is used at subject position.
     * @throws \Exception if a non-URI or non-BlankNode is used at predicate position.
     */
    protected function rdfPhpToStatementIterator(array $rdfPhp)
    {
        $statements = array();

        // go through all subjects
        foreach ($rdfPhp as $subject => $predicates) {
            // predicates associated with the subject
            foreach ($predicates as $property => $objects) {
                // object(s)
                foreach ($objects as $object) {
                    /**
                     * Create subject node
                     */
                    if (true === $this->RdfHelpers->simpleCheckURI($subject)) {
                        $s = $this->nodeFactory->createNamedNode($subject);
                    } elseif (true === $this->RdfHelpers->simpleCheckBlankNodeId($subject)) {
                        $s = $this->nodeFactory->createBlankNode($subject);
                    } else {
                        // should not be possible, because EasyRdf is able to check for invalid subjects.
                        throw new \Exception('Only URIs and blank nodes are allowed as subjects.');
                    }

                    /**
                     * Create predicate node
                     */
                    if (true === $this->RdfHelpers->simpleCheckURI($property)) {
                        $p = $this->nodeFactory->createNamedNode($property);
                    } elseif (true === $this->RdfHelpers->simpleCheckBlankNodeId($property)) {
                        $p = $this->nodeFactory->createBlankNode($property);
                    } else {
                        // should not be possible, because EasyRdf is able to check for invalid predicates.
                        throw new \Exception('Only URIs and blank nodes are allowed as predicates.');
                    }

                    /*
                     * Create object node
                     */
                    // URI
                    if ($this->RdfHelpers->simpleCheckURI($object['value'])) {
                        $o = $this->nodeFactory->createNamedNode($object['value']);

                    // datatype set
                    } elseif (isset($object['datatype'])) {
                        $o = $this->nodeFactory->createLiteral($object['value'], $object['datatype']);

                    // lang set
                    } elseif (isset($object['lang'])) {
                        $o = $this->nodeFactory->createLiteral(
                            $object['value'],
                            'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString',
                            $object['lang']
                        );
                    // if no information about the object was provided, assume its a simple string
                    } else {
                        $o = $this->nodeFactory->createLiteral($object['value']);
                    }

                    // build and add statement
                    $statements[] = $this->statementFactory->createStatement($s, $p, $o);
                }
            }
        }

        return $this->statementIteratorFactory->createStatementIteratorFromArray($statements);
    }
}
