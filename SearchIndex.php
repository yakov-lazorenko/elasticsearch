<?php

namespace App\Services\Search;

use GuzzleHttp;

/**
 *   Class SearchIndex.
 *   Tool for full text search using Elasticsearch.
 *
 *   The SearchIndex class implements functionality for full-text search 
 *   using Elasticsearch, creating an index, adding and updating documents.
 */
class SearchIndex
{
    /**
     *   @var string Index Name
     */
    protected $index_name;

    /**
     *   @var string Host Name
     */
    protected $host;

    /**
     *   @var integer HTTP request timeout value in seconds.
     */
    protected $request_timeout;

    /**
     *   @var string Index config in JSON format.
     */
    protected $config_json = null;

    /**
     *   @var DocumentSearchHelper Full text search tool.
     */
    protected $documentSearchHelper;

    /**
     *   @var DocumentWriter Tool for creating, updating 
     *   and deleting documents in Elasticsearch index.
     */
    protected $documentWriter;

    /**
     *   @var DocumentReader Tool for retrieving documents from Elasticsearch index.
     */
    protected $documentReader;

    /**
     *   @var AnalyzeApi Tool for using Elasticsearch Analyze Api.
     */
    protected $analyzeApi;

    /**
     *   @var string Error Message.
     */
    protected $error;


    /**
     *   Create a new SearchIndex class.
     *
     *   @param string $index_name Index Name
     *   @param string $host Host Name
     *   @param string $config_json Index config in JSON format.
     *   @param integer $request_timeout HTTP request timeout value in seconds.
     *   @return SearchIndex
     */
    public function __construct(
        $index_name = null,
        $host = null,
        $config_json = null,
        $request_timeout = null
    ){
        $this->setIndexName($index_name ?? 'default');
        $this->setHost($host ?? 'http://localhost:9200');
        $this->setRequestTimeout($request_timeout ?? 5);
        $this->setConfigJson($config_json);
        $this->setError(null);

        $this->documentSearchHelper = new DocumentSearchHelper($this);
        $this->documentReader = new DocumentReader($this);
        $this->documentWriter = new DocumentWriter($this);
        $this->analyzeApi = new AnalyzeApi($this);
    }


    /**
     *   Returns search results that match the DSL query.
     *
     *   @param array|string $query DSL query in array or JSON string format.
     *   @param array $options Options
     *   @return string|array|null Search results or null if errors occurred.
     *
     *   If $options['raw_output'] = true, function returns raw output string
     *   from Elasticsearch API.
     */
    public function search($query, $options = null)
    {
        return $this->documentSearchHelper->search($query, $options);
    }


    /**
     *   Returns the number of documents that match the DSL query.
     *
     *   @param array|string $query DSL query in array or JSON string format.
     *   @return integer|null Number of documents or null if errors occurred.
     */
    public function count($query)
    {
        return $this->documentSearchHelper->count($query);
    }


    /**
     *   Full-text search by keywords in a given document field.
     *
     *   @param string $keywords Keywords for search.
     *   @param string $field_name Field name.
     *   @param integer|null $limit
     *   @param integer|null $offset
     *   @param boolean $raw_output If $raw_output = true, function returns 
     *   raw output string from Elasticsearch API.
     *   @return string|array|null Search results or null if errors occurred.
     */
    public function searchSimple(
        $keywords,
        $field_name,
        $limit = null,
        $offset = 0,
        $raw_output = false
    ){
        return $this->documentSearchHelper
                    ->searchSimple(
                        $keywords,
                        $field_name,
                        $limit,
                        $offset,
                        $raw_output
                    );
    }



    /**
     *   Returns true if index exists.
     *
     *   @return boolean
     */
    public function isIndexExists()
    {
        $url = $this->getHost() . '/'
            . $this->getIndexName();

        $client = new GuzzleHttp\Client();

        try {
            $response = $client->request('HEAD', $url, ['timeout' => $this->getRequestTimeout()]);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return false;
        }

        if (empty($response) || $response->getStatusCode() != 200) {
            $this->searchIndex->setError('Error in SearchIndex::isIndexExists()');
            return false;
        }
        return true;
    }


    /**
     *   Returns full list of indexes.
     *
     *   @return string
     */
    public function showIndexesList()
    {
        $url = $this->getHost() . '/_cat/indices?v&pretty';
        $client = new GuzzleHttp\Client();

        try {
            $response = $client->request('GET', $url, ['timeout' => $this->getRequestTimeout()]);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return null;
        }

        if (empty($response) || $response->getStatusCode() != 200 || empty($response->getBody())) {
            $this->searchIndex->setError('Error in SearchIndex::showIndexesList()');
            return null;
        }
        return (string)$response->getBody();
    }


    /**
     *   Creates an index.
     *
     *   @return boolean Returns true on successful operation or 
     *   false if errors occured.
     */
    public function createIndex()
    {
        $url = $this->getHost() . '/'
            . $this->getIndexName() . '?pretty';

        $client = new GuzzleHttp\Client();

        if ($this->getConfigJson()) {
            $params = [
                'body' => $this->getConfigJson(),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => $this->getRequestTimeout()
            ];
        } else {
            $params = ['timeout' => $this->getRequestTimeout()];
        }

        try {
            $response = $client->request('PUT', $url, $params);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return false;
        }

        if (empty($response) || !in_array($response->getStatusCode(), [200, 201]) || empty($response->getBody())) {
            $this->searchIndex->setError('Error in SearchIndex::createIndex()');
            return false;
        }
        return true;
    }


    /**
     *   Deletes an index.
     *
     *   @return boolean Returns true on successful operation or 
     *   false if errors occured.
     */
    public function deleteIndex()
    {
        $url = $this->getHost() . '/'
            . $this->getIndexName() . '?pretty';

        $client = new GuzzleHttp\Client();

        try {
            $response = $client->request('DELETE', $url, ['timeout' => $this->getRequestTimeout()]);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return false;
        }

        if (empty($response) || $response->getStatusCode() != 200) {
            $this->searchIndex->setError('Error in SearchIndex::deleteIndex()');
            return false;
        }
        return true;
    }


    /**
     *   Receiving a document by its ID.
     *
     *   @param integer $id
     *   @return array|null Document.
     */
    public function getDocumentById($id)
    {
        return $this->documentReader->getDocumentById($id);
    }


    /**
     *   Get all documents.
     *
     *   @param integer $limit
     *   @param integer $offset
     *   @param array $options
     *   @return array|null Array of documents.
     */
    public function getAllDocuments($limit = null, $offset = 0, $options = null)
    {
        return $this->documentReader->getAllDocuments($limit, $offset, $options);
    }


    /**
     *   Get the number of documents.
     *
     *   @return integer|null Number of documents or null if errors occurred.
     */
    public function getAllDocumentsCount()
    {
        return $this->documentReader->getAllDocumentsCount();
    }


    /**
     *   Creates or updates a document.
     *
     *   @param array $document Document
     *   @return boolean Returns true on successful operation or
     *   false if errors occured.
     */
    public function createOrUpdateDocument($document)
    {
        return $this->documentWriter->createOrUpdateDocument($document);
    }


    /**
     *   Creates a set of documents in one request to the API 
     *   using Elasticsearch Bulk API.
     *
     *   @param array $documents Array of documents.
     *   @return boolean Returns true on successful operation or
     *   false if errors occured.
     */
    public function createDocuments($documents)
    {
        return $this->documentWriter->createDocuments($documents);
    }


    /**
     *   Removal of document.
     *
     *   @param integer $id
     *   @return boolean Returns true on successful operation or 
     *   false if errors occured.
     */
    public function deleteDocument($id)
    {
        return $this->documentWriter->deleteDocument($id);
    }


    /**
     *   Performs analysis on a text string and returns the resulting tokens.
     *
     *   @param array|string $query DSL query in array or JSON string format.
     *   @param string|null $indexName Index name.
     *   @return array|null Analysis results or null if errors occurred.
     */
    public function analyze($query, $indexName = null)
    {
        return $this->analyzeApi->analyze($query, $indexName);
    }


    public function getIndexName()
    {
        return $this->index_name;
    }


    public function setIndexName($index_name)
    {
        $this->index_name = $index_name;
    }


    public function getHost()
    {
        return $this->host;
    }


    public function setHost($host)
    {
        $this->host = $host;
    }


    public function getRequestTimeout()
    {
        return $this->request_timeout;
    }


    public function setRequestTimeout($request_timeout)
    {
        $this->request_timeout = $request_timeout;
    }


    public function getConfigJson()
    {
        return $this->config_json;
    }


    public function setConfigJson($config_json)
    {
        $this->config_json = !empty($config_json) ? $config_json : null;
    }


    public function getError()
    {
        return $this->error;
    }


    public function setError($error)
    {
        $this->error = $error;
    }

}
