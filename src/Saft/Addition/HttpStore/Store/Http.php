<?php

namespace Saft\Addition\HttpStore\Store;

use Curl\Curl;
use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\Statement;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementIterator;
use Saft\Rdf\StatementIteratorFactory;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\RdfHelpers;
use Saft\Sparql\Query\AbstractQuery;
use Saft\Sparql\Query\QueryFactory;
use Saft\Sparql\Query\QueryFactoryImpl;
use Saft\Sparql\Result\ResultFactory;
use Saft\Sparql\Result\ResultFactoryImpl;
use Saft\Store\AbstractSparqlStore;
use Saft\Store\Store;

/**
 * SparqlStore implementation of a client which handles store operations via HTTP. It is able to determine some
 * server types by checking response header.
 */
class Http extends AbstractSparqlStore
{
    /**
     * Adapter options
     *
     * @var array
     */
    protected $configuration = null;

    /**
     * @var Curl\Curl
     */
    protected $httpClient = null;

    /**
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var RdfHelpers
     */
    protected $rdfHelpers;

    /**
     * @var QueryFactory
     */
    protected $queryFactory;

    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var StatementFactory
     */
    protected $statementFactory;

    /**
     * @var StatementIteratorFactory
     */
    protected $statementIteratorFactory;

    /**
     * Constructor. Dont forget to call setClient and provide a working GuzzleHttp\Client instance.
     *
     * @param NodeFactory              $nodeFactory
     * @param StatementFactory         $statementFactory
     * @param QueryFactory             $queryFactory
     * @param ResultFactory            $resultFactory
     * @param StatementIteratorFactory $statementIteratorFactory
     * @param RdfHelpers                $rdfHelpers
     * @param array                    $configuration Array containing database credentials
     * @throws \Exception              If HTTP store requires the PHP ODBC extension to be loaded.
     */
    public function __construct(
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        QueryFactory $queryFactory,
        ResultFactory $resultFactory,
        StatementIteratorFactory $statementIteratorFactory,
        RdfHelpers $rdfHelpers,
        array $configuration
    ) {
        $this->RdfHelpers = $rdfHelpers;

        $this->configuration = $configuration;

        // Open connection and, if possible, authenticate on server
        $this->openConnection();

        $this->nodeFactory = $nodeFactory;
        $this->statementFactory = $statementFactory;
        $this->queryFactory = $queryFactory;
        $this->resultFactory = $resultFactory;
        $this->statementIteratorFactory = $statementIteratorFactory;

        parent::__construct(
            $nodeFactory,
            $statementFactory,
            $queryFactory,
            $resultFactory,
            $statementIteratorFactory,
            $rdfHelpers
        );
    }

    /**
     * Using digest authentication to authenticate user on the server.
     *
     * @param  string $authUrl  URL to authenticate.
     * @param  string $username Username to access.
     * @param  string $password Password to access.
     * @throws \Exception If response
     */
    protected function authenticateOnServer($authUrl, $username, $password)
    {
        $response = $this->sendDigestAuthentication($authUrl, $username, $password);

        $httpCode = $response->getHttpCode();

        // If status code is not 200, something went wrong
        if (200 !== $httpCode) {
            throw new \Exception('Response with Status Code [' . $httpCode . '].', 500);
        }
    }

    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Checks, what rights the current user has to query and update graphs and triples. Be aware, that method
     * could polute your store by creating test graphs.
     *
     * @return array An array with key value pairs. Keys are graphUpdate, tripleQuerying and tripleUpdate.
     *               The values are boolean values, which depend on the according right if they are true or
     *               false.
     * @todo Implement a safer way to check, if the current user can create and drop a graph
     * @todo Implement a safer way to check, if the current user can create a triple inside a graph
     *       Problem here is to get a graph, in which you have write access.
     */
    public function getRights()
    {
        $rights = array(
            'graphUpdate' => false,
            'tripleQuerying' => false,
            'tripleUpdate' => false
        );

        // generate a unique graph URI which we will use later on for our tests.
        $graph = 'http://saft/'. hash('sha1', rand(0, time()) . microtime(true)) .'/';

        /*
         * check if we can create and drop graphs
         */
        try {
            $this->query('CREATE GRAPH <'. $graph .'>');
            $this->query('DROP GRAPH <'. $graph .'>');
            $rights['graphUpdate'] = true;
        } catch (\Exception $e) {
            // ignore exception here and assume we could not create or drop the graph.
        }

        /*
         * check if we can query triples
         */
        try {
            $this->query('SELECT ?g { GRAPH ?g {?s ?p ?o} } LIMIT 1');
            $rights['tripleQuerying'] = true;
        } catch (\Exception $e) {
            // ignore exception here and assume we could not query anything.
        }

        /*
         * check if we can create and update queries.
         */
        try {
            if ($rights['graphUpdate']) {
                // create graph
                $this->query('CREATE GRAPH <'. $graph .'>');

                // create a simple triple
                $this->query('INSERT DATA { GRAPH <'. $graph .'> { <'. $graph .'1> <'. $graph .'2> "42" } }');

                // remove all triples
                $this->query('WITH <'. $graph .'> DELETE { ?s ?p ?o }');

                // drop graph
                $this->query('DROP GRAPH <'. $graph .'>');

                $rights['tripleUpdate'] = true;
            }
        } catch (\Exception $e) {
            // ignore exception here and assume we could not update a triple.
            // whatever happens, try to remove the fresh graph.
            try {
                $this->query('DROP GRAPH <'. $graph .'>');
            } catch (\Exception $e) {
            }
        }

        return $rights;
    }

    /**
     * @return array Empty
     * TODO implement getStoreDescription
     */
    public function getStoreDescription()
    {
        return array();
    }

    /**
     * Checks if a certain graph is available in the store.
     *
     * @param  Node $graph URI of the graph to check if it is available.
     * @return boolean True if graph is available, false otherwise.
     * @todo   find a more precise way to check if a graph is available.
     */
    public function isGraphAvailable(Node $graph)
    {
        $graphs = $this->getGraphs();
        return isset($graphs[$graph->getUri()]);
    }

    /**
     * Establish a connection to the endpoint and authenticate.
     *
     * @return Client Setup HTTP client.
     */
    public function openConnection()
    {
        if (null == $this->httpClient) {
            return false;
        }

        $configuration = array_merge(array(
            'authUrl' => '',
            'password' => '',
            'queryUrl' => '',
            'username' => ''
        ), $this->configuration);

        // authenticate only if an authUrl was given.
        if ($this->RdfHelpers->simpleCheckURI($configuration['authUrl'])) {
            $this->authenticateOnServer(
                $configuration['authUrl'],
                $configuration['username'],
                $configuration['password']
            );
        }

        // check query URL
        if (false === $this->RdfHelpers->simpleCheckURI($configuration['queryUrl'])) {
            throw new \Exception('Parameter queryUrl is not an URI or empty: '. $configuration['queryUrl']);
        }
    }

    /**
     * This method sends a SPARQL query to the store.
     *
     * @param  string     $query            The SPARQL query to send to the store.
     * @param  array      $options optional It contains key-value pairs and should provide additional
     *                                      introductions for the store and/or its adapter(s).
     * @return Result     Returns result of the query. Its type depends on the type of the query.
     * @throws \Exception     If query is no string.
     * @throws \Exception     If query is malformed.
     * @throws StoreException If server returned an error.
     * @todo add support for DESCRIBE queries
     */
    public function query($query, array $options = array())
    {
        $queryObject = $this->queryFactory->createInstanceByQueryString($query);
        $queryParts = $queryObject->getQueryParts();
        $queryType = $this->rdfHelpers->getQueryType($query);

        /**
         * CONSTRUCT query
         */
        if ('constructQuery' == $queryType) {
            $receivedResult = $this->sendSparqlSelectQuery($this->configuration['queryUrl'], $query);

            $resultArray = $this->transformResultToArray($receivedResult);

            if (isset($resultArray['results']['bindings'])) {
                if (0 < count($resultArray['results']['bindings'])) {

                    $statements = array();

                    // important: we assume the bindings list is ORDERED!
                    foreach ($resultArray['results']['bindings'] as $entries) {
                        $statements[] = $this->statementFactory->createStatement(
                            $this->transformEntryToNode($entries['s']),
                            $this->transformEntryToNode($entries['p']),
                            $this->transformEntryToNode($entries['o'])
                        );
                    }

                    return $this->resultFactory->createStatementResult($statements);
                }
            }

            return $this->resultFactory->createEmptyResult();

        /**
         * SELECT query
         */
        } elseif ('selectQuery' == $queryType) {
            $receivedResult = $this->sendSparqlSelectQuery($this->configuration['queryUrl'], $query);

            $resultArray = $this->transformResultToArray($receivedResult);

            $entries = array();

            /**
             * go through all bindings and create according objects for SetResult instance.
             *
             * $bindingParts will look like:
             *
             * array(
             *      's' => array(
             *          'type' => 'uri',
             *          'value' => '...'
             *      ), ...
             * )
             */
            foreach ($resultArray['results']['bindings'] as $bindingParts) {
                $newEntry = array();

                foreach ($bindingParts as $variable => $part) {
                    $newEntry[$variable] = $this->transformEntryToNode($part);
                }

                $entries[] = $newEntry;
            }

            $return = $this->resultFactory->createSetResult($entries);
            $return->setVariables($resultArray['head']['vars']);
            return $return;

        /**
         * SPARPQL Update query
         */
        } else {
            $receivedResult = $this->sendSparqlUpdateQuery($this->configuration['queryUrl'], $query);
            // transform object to array
            if (is_object($receivedResult)) {
                $decodedResult = json_decode(json_encode($receivedResult), true);
            // transform json string to array
            } else {
                $decodedResult = json_decode($receivedResult, true);
            }

            if ('askQuery' === $queryType) {
                if (true === isset($decodedResult['boolean'])) {
                    return $this->resultFactory->createValueResult($decodedResult['boolean']);

                // assumption here is, if a string was returned, something went wrong.
                } elseif (0 < strlen($receivedResult)) {
                    throw new \Exception($receivedResult);

                } else {
                    return $this->resultFactory->createEmptyResult();
                }

            // usually a SPARQL result does not return a string. if it does anyway, assume there is an error.
            } elseif (null === $decodedResult && 0 < strlen($receivedResult)) {
                throw new \Exception($receivedResult);

            } else {
                return $this->resultFactory->createEmptyResult();
            }
        }
    }

    /**
     * Send digest authentication to the server via GET.
     *
     * @param  string $username
     * @param  string $password optional
     * @return string
     */
    public function sendDigestAuthentication($url, $username, $password = null)
    {
        $this->httpClient->setDigestAuthentication($username, $password);

        return $this->httpClient->get($url);
    }

    /**
     * Sends a SPARQL select query to the server.
     *
     * @param string $url
     * @param string $query
     * @return string Response of the POST request.
     */
    public function sendSparqlSelectQuery($url, $query)
    {
        // because you can't just fire multiple requests with the same CURL class instance
        // we have to be creative and re-create the class everytime a request is to be send.
        // FYI: https://github.com/php-curl-class/php-curl-class/issues/326
        $class = get_class($this->httpClient);
        $phpVersionToOld = version_compare(phpversion(), '5.5.11', '<=');
        // only reinstance the class if its not mocked
        if ($phpVersionToOld && false === strpos($class, 'Mockery')) {
            $this->httpClient = new $class();
        }

        $this->httpClient->setHeader('Accept', 'application/sparql-results+json');
        $this->httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded');

        return $this->httpClient->post($url, array('query' => $query));
    }

    /**
     * Sends a SPARQL update query to the server.
     *
     * @param string $url
     * @param string $query
     * @return string Response of the GET request.
     */
    public function sendSparqlUpdateQuery($url, $query)
    {
        // because you can't just fire multiple requests with the same CURL class instance
        // we have to be creative and re-create the class everytime a request is to be send.
        // FYI: https://github.com/php-curl-class/php-curl-class/issues/326
        $class = get_class($this->httpClient);
        $phpVersionToOld = version_compare(phpversion(), '5.5.11', '<=');
        // only reinstance the class if its not mocked
        if ($phpVersionToOld && false === strpos($class, 'Mockery')) {
            $this->httpClient = new $class;
        }

        // TODO extend Accept headers to further formats
        $this->httpClient->setHeader('Accept', 'application/sparql-results+json');
        $this->httpClient->setHeader('Content-Type', 'application/sparql-update');

        return $this->httpClient->get($url, array('query' => $query));
    }

    /**
     * @param \Curl\Curl $httpClient
     */
    public function setClient(Curl $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Transforms server result to aray.
     *
     * @param mixed $receivedResult
     * @return array
     */
    public function transformResultToArray($receivedResult)
    {
        // transform object to array
        if (is_object($receivedResult)) {
            return json_decode(json_encode($receivedResult), true);
        // transform json string to array
        } else {
            return json_decode($receivedResult, true);
        }
    }
}
