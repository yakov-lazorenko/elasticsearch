<?php

namespace App\Services\Search;

use GuzzleHttp;

/**
 *   Class AnalyzeApi.
 *   Tool for using Elasticsearch Analyze API.
 */
class AnalyzeApi
{
    /**
     *   @var SearchIndex
     */
    protected $searchIndex;


    /**
     *   Create a new AnalyzeApi class.
     *
     *   @param SearchIndex $searchIndex
     *   @return AnalyzeApi
     */
    public function __construct($searchIndex)
    {
        $this->searchIndex = $searchIndex;
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
        $url = $this->searchIndex->getHost();
        if ($indexName) {
            $url .= "/$indexName";
        }
        $url .= '/_analyze?pretty';

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
            $response = $client->request('GET', $url, $params);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return null;
        }

        if (empty($response) || $response->getStatusCode() != 200 || empty($response->getBody())) {
            return null;
        }
        if (!($data = \json_decode($response->getBody(), true))) {
            return null;
        }
        return $data;
    }
}
