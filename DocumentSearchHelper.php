<?php

namespace App\Services\Search;

use GuzzleHttp;

/**
 *   Class DocumentSearchHelper.
 *   Full text search tool using Elasticsearch Search API.
 */
class DocumentSearchHelper
{
    /**
     *   @var SearchIndex
     */
    protected $searchIndex;


    /**
     *   Create a new DocumentSearchHelper class.
     *
     *   @param SearchIndex $searchIndex
     *   @return DocumentSearchHelper
     */
    public function __construct($searchIndex)
    {
        $this->searchIndex = $searchIndex;
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
        $url = $this->searchIndex->getHost() . '/'
            . $this->searchIndex->getIndexName() . '/'
            . '_doc/_search?pretty';

        $client = new GuzzleHttp\Client();

        if (is_array($query)) {
            $params = ['json' => $query, 'timeout' => $this->searchIndex->getRequestTimeout()];
        } elseif (is_string($query)) {
            $params = [
                'body' => $query,
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => $this->searchIndex->getRequestTimeout()
            ];
        } else {
            $this->searchIndex->setError('Invalid query value.');
            return null;
        }

        try {
            $response = $client->request('POST', $url, $params);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return null;
        }

        if (empty($response) || $response->getStatusCode() != 200 || empty($response->getBody())) {
            return null;
        }

        if (!empty($options['raw_output'])){
            return (string)$response->getBody();
        }
        $data = \json_decode($response->getBody(), true);
        if (!$data || empty($data['hits']) || !isset($data['timed_out']) ||
            $data['timed_out'] || empty($data['hits']['hits'])) {
            return null;
        }

        $documents = [];
        $max_score = $data['hits']['max_score'] ?? null;
        $total = $data["hits"]["total"]["value"] ?? null;

        foreach ($data['hits']['hits'] as $document) {
            $document['_source']['__search_info']['score'] = $document['_score'] ?? null;
            $documents[] = $document['_source'];
        }
  
        return [
            'documents' => $documents,
            'total' => $total,
            'max_score' => $max_score,
        ];
    }


    /**
     *   Returns the number of documents that match the DSL query.
     *
     *   @param array|string $query DSL query in array or JSON string format.
     *   @return integer|null Number of documents or null if errors occurred.
     */
    public function count($query)
    {
        $url = $this->searchIndex->getHost() . '/'
            . $this->searchIndex->getIndexName() . '/'
            . '/_count?pretty';

        $client = new GuzzleHttp\Client();

        if (is_array($query)) {
            $params = ['json' => $query, 'timeout' => $this->searchIndex->getRequestTimeout()];
        } elseif (is_string($query)) {
            $params = [
                'body' => $query,
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => $this->searchIndex->getRequestTimeout()];
        } else {
            return null;
        }

        try {
            $response = $client->request('POST', $url, $params);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return null;
        }

        if (empty($response) || $response->getStatusCode() != 200 || empty($response->getBody())) {
            return null;
        }
        $data = \json_decode($response->getBody(), true);
        if (!$data || !isset($data['count']) || !is_integer($data['count'])) {
            return null;
        }
        return $data['count'];
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
        $limit = $limit ?? 10;

        $query = [
           "from" => $offset,
           "size" => $limit,
        ];

        if (empty($keywords)){
            $query["sort"] = [
                [ "id" => "asc" ]
            ];
            $query["query"] = [
                "match_all" => new \stdClass, // ["boost" => 1.0],
            ];
        } else {
            $query["query"] = [
                "match" => [$field_name => $keywords]
            ];
        }

        $options = $raw_output ? ['raw_output' => true] : [];
        return $this->search($query, $options);
    }

}
