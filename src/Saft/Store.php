<?php

namespace Saft;

class Store
{
    /**
     * @var \Saft\Store\Adapter\AbstractAdapter
     */
    protected $adapter;

    /**
     * @var \Saft\Cache
     */
    protected $cache;

    /**
     * @var array
     */
    protected $graphs;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var \Enable\QueryCache
     */
    protected $queryCache;

    /**
     * Constructor
     *
     * @param array       $config Store related configuration array.
     * @param \Saft\Cache $cache  Cache instance.
     */
    public function __construct(array $config, \Saft\Cache $cache)
    {
        $this->init($config, $cache);
    }

    /**
     *
     * @param
     * @return
     * @throw
     */
    public function addGraph($graphUri)
    {
        if (true === \Saft\Rdf\NamedNode::check($graphUri)) {
            $this->adapter->addGraph($graphUri);

            // delete cache entry to force loading all graph uris again
            $this->cache->delete("sto". $this->id ."_availableGraphUris");

            $this->getAvailableGraphUris();
        } else {
            throw new \Exception("Given \$graphUri is not a valid URI or empty.");
        }
    }

    /**
     * Add multiple triples to a graph.
     *
     * @param  string $graphUri URI of the graph to add triples.
     * @param  array  $triples  Array of triples to add.
     * @return ODBC resource
     * @throw
     */
    public function addMultipleTriples($graphUri, array $triples)
    {
        $this->invalidateSubjectResources($graphUri, $triples);

        return $this->adapter->addMultipleTriples($graphUri, $triples);
    }

    /**
     * Add a triple.
     *
     * @param  string $graphUri  URI of the graph to add triple
     * @param  string $subject   URI of the subject to add
     * @param  string $predicate URI of the predicate to add
     * @param  array  $object    Array with data of the object to add
     * @return ODBC resource
     * @throw  \Exception
     */
    public function addTriple($graphUri, $subject, $predicate, array $object)
    {
        return $this->addMultipleTriples(
            $graphUri,
            array(array($subject, $predicate, $object))
        );
    }

    /**
     *
     * @param string $graphUri
     * @return
     * @throw \Exception TODO
     */
    public function clearGraph($graphUri)
    {
        return $this->adapter->clearGraph($graphUri);
    }

    /**
     * Drops an existing graph.
     *
     * @param  string $graphUri URI of the graph to drop.
     * @return void
     * @throw  \Exception
     */
    public function dropGraph($graphUri)
    {
        if (true === \Saft\Rdf\NamedNode::check($graphUri)) {
            $this->adapter->dropGraph($graphUri);

            /**
             * Reset cache
             */
            // delete according query cache entries
            $this->queryCache->invalidateByGraphUri($graphUri);

            // delete cache entry to force loading all graph uris again
            $this->cache->delete("sto". $this->id ."_availableGraphUris");

            $this->getAvailableGraphUris();
        } else {
            throw new \Exception("Given \$graphUri is not a valid URI or empty.");
        }
    }

    /**
     * Drops multiple triples.
     *
     * @param  string $graphUri URI of the graph to drop triples
     * @param  array  $triples  Array of triples to drop
     * @return ODBC resource Last used ODBC resource
     * @throws \Exception
     */
    public function dropMultipleTriples($graphUri, array $triples)
    {
        $this->invalidateSubjectResources($graphUri, $triples);

        return $this->adapter->dropMultipleTriples(
            $graphUri,
            $triples
        );
    }

    /**
     * Drops a triple.
     *
     * @param  string $graphUri  URI of the graph to drop the triple
     * @param  string $subject   URI of the subject to drop
     * @param  string $predicate URI of the predicate to drop
     * @param  array  $object    Array with data of the object to drop
     * @return ODBC resource
     * @throw  \Exception
     */
    public function dropTriple($graphUri, $subject, $predicate, array $object)
    {
        return $this->dropMultipleTriples(
            $graphUri,
            array(array($subject, $predicate, $object))
        );
    }

    /**
     * Returns a list of available graph URIs.
     *
     * @return array List of available graph URIs.
     * @throw  \Exception
     */
    public function getAvailableGraphUris()
    {
        if (false === $this->cache->get("sto". $this->id ."_availableGraphUris")) {
            $this->cache->set(
                "sto". $this->id ."_availableGraphUris",
                $this->adapter->getAvailableGraphUris()
            );
        }

        return $this->cache->get("sto". $this->id ."_availableGraphUris");
    }

    /**
     * Returns current \Enable\Cache instance.
     *
     * @return \Enable\Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Returns an instance of an according graph to the store.
     *
     * @param  string $graphUri URI of the graph to get.
     * @return \Enable\Store\Graph|null
     * @throw  \Exception
     */
    public function getGraph($graphUri)
    {
        if (true === $this->isGraphAvailable($graphUri)) {
            // conserve a once created graph instance and return it, if anybody
            // else want to later on again
            if (false === isset($this->graphs[$graphUri])) {
                $this->graphs[$graphUri] = new \Saft\Store\Graph(
                    $this,
                    $graphUri,
                    $this->cache
                );
            }
            return $this->graphs[$graphUri];

        } else {
            throw new \Exception("Graph $graphUri is not available.");
        }
    }

    /**
     * Return store id. Store ID based on the config and can be used to get
     * randomness.
     *
     * @return string Hash of the given $config
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the instance of QueryCache.
     *
     * @return \Enable\QueryCache
     */
    public function getQueryCache()
    {
        return $this->queryCache;
    }

    /**
     * Either counts triples of a certain graph or all triples of all graphs
     * of the store.
     *
     * @param  string $graphUri
     * @return int number of triples
     * @throw  \Exception
     */
    public function getTripleCount($graphUri = "")
    {
        if (true === \Saft\Rdf\NamedNode::check($graphUri)) {
            return $this->adapter->getTripleCount($graphUri);
        } else {
            $graphUris = $this->getAvailableGraphUris();
            $count = 0;

            foreach ($graphUris as $graphUri) {
                $count += $this->adapter->getTripleCount($graphUri);
            }

            return $count;
        }
    }

    /**
     *
     * @param string $graphUri
     * @return
     * @throw
     * @todo port this function completly!
     */
    public function getTitleHelper($graphUri)
    {
        if (false === isset($this->_titleHelpers[$graphUri])) {
            // check if graph is available, if so, instantiate it
            if (false === is_null($this->getGraph($graphUri))) {
                $this->_titleHelpers[$graphUri] = new \Enable\Rdf\Property\TitleHelper(
                    $this->getGraph($graphUri),
                    $this->cache
                );
            } else {
                throw new \Exception(
                    "TitleHelper needs a graph, but no graph with given \$graphUri found."
                );
            }
        }

        return $this->_titleHelpers[$graphUri];
    }

    /**
     * Invalidate all query cache entries which refering to given resources (subject).
     *
     * @param  string $graphUri    URI of the graph which is related to the triples
     * @param  array  $tripleArray PHP-array which contains the triples to be created. They will be invalidated first.
     *                           created. They will be invalidated first.
     * @return void
     * @throw  \Exception
     */
    public function invalidateSubjectResources($graphUri, array $tripleArray)
    {
        $subjectUris = array();

        $graph = $this->getGraph($graphUri);
        $graphId = $this->queryCache->generateShortId($graphUri);

        // collect all relevant subject URIs
        foreach ($tripleArray as $triple) {
            // check if subject URI was invalidate before, to prevent obsolete work
            if (false === isset($subjectUris[$triple[0]])) {
                // invalidate resource (triple subject)
                $graph->invalidateResource($triple[0]);

                // remember triple subject
                $subjectUris[$triple[0]] = $triple[0];
            }
        }

        // get according query ids
        $queryIds = $this->cache->get($graphId);

        // check if something is there to delete
        if (false !== $queryIds) {
            // get content according to the queryId
            foreach ($queryIds as $queryId) {
                // get query container
                $queryContainer = $this->cache->get($queryId);

                foreach ($queryContainer["triplePattern"][$graphId] as $pattern) {
                    foreach ($subjectUris as $subjectUri) {
                        $subjectUriId = $this->queryCache->generateShortId($subjectUri, false);

                        // look for the hashed subject URI of a joker sign on the
                        // subjects position ...
                        if (false !== strpos($pattern, $graphId ."_". $subjectUriId)
                            || false !== strpos($pattern, $graphId ."_*_")
                        ) {
                            // ... in case a match takes place, remove everything,
                            // which is related to the according query of the current
                            // pattern
                            $this->queryCache->invalidateByQuery($queryContainer["query"]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Checks if a certain graph is available in the store.
     *
     * @param  string $graphUri URI of the graph to check if it is available.
     * @return boolean True if graph is available, false otherwise.
     */
    public function isGraphAvailable($graphUri)
    {
        return $this->adapter->isGraphAvailable($graphUri);
    }

    /**
     * Initialize store instance
     *
     * @param  array       $config
     * @param  \Saft\Cache
     * @return
     * @throw
     */
    public function init(array $config, \Saft\Cache $cache)
    {
        if (true === isset($config["type"])) {
            switch ($config["type"]) {
                /**
                 * Http
                 */
                case "http":
                    $this->adapter = new \Saft\Store\Adapter\Http($config);
                    break;

                /**
                 * Virtuoso
                 */
                case "virtuoso":
                    $this->adapter = new \Saft\Store\Adapter\Virtuoso($config);
                    break;

                /**
                 * Unknown adapter type.
                 */
                default:
                    throw new \Exception("Unknown adapter type given.");
                    break;
            }

        } else {
            throw new \Exception("Unknown adapter type given.");
        }

        $this->cache = $cache;

        $this->graphs = array();

        $this->id = hash("sha1", json_encode($config) . microtime() . rand(0, time()));

        $this->queryCache = new \Saft\QueryCache($this->cache);
    }

    /**
     * Send SPARQL query to the server.
     *
     * @param  string $query   Query to execute
     * @param  array  $options optional Options to configure the query-execution and the result.
     * @return array
     * @throw  \Exception
     */
    public function sparql($query, array $options = array())
    {
        // if requested, bypass QueryCache
        if (true === isset($options["useQueryCache"])
            && false === $options["useQueryCache"]
        ) {
            return $this->adapter->sparql($query, $options);
        }

        $queryId = $this->queryCache->generateShortId($query);
        $queryResult = $this->cache->get($queryId);

        if (false === $queryResult) {
            // execute the query in the store, save the result and init cache entry
            $this->queryCache->rememberQueryResult(
                $query,
                $this->adapter->sparql($query, $options)
            );

            $queryResult = $this->cache->get($queryId);
        }

        return $queryResult["result"];
    }
}
