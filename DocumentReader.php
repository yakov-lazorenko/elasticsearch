<?php

namespace App\Services\Search;

use GuzzleHttp;

/**
 *   Class DocumentReader.
 *   Tool for retrieving documents from Elasticsearch index.
 */
class DocumentReader
{
    /**
     *   @var SearchIndex
     */
    protected $searchIndex;


    /**
     *   Create a new DocumentReader class.
     *
     *   @param SearchIndex $searchIndex
     *   @return DocumentReader
     */
    public function __construct($searchIndex)
    {
        $this->searchIndex = $searchIndex;
    }


    /**
     *   Receiving a document by its ID.
     *
     *   @param integer $id
     *   @return array|null Document.
     */
    public function getDocumentById($id)
    {
        $url = $this->searchIndex->getHost() . '/'
            . $this->searchIndex->getIndexName() . '/'
            . '_doc/' . $id . '?pretty';

        $client = new GuzzleHttp\Client();

        try {
            $response = $client->request('GET', $url, ['timeout' => $this->searchIndex->getRequestTimeout()]);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return null;
        }

        if (empty($response) || $response->getStatusCode() != 200 || empty($response->getBody())) {
            return null;
        }
        $data = \json_decode($response->getBody(), true);
        // if doc exists: "found" = true, "_source" - not empty
        if (!$data || empty($data['found']) || empty($data['_source'])) {
            return null;
        }
        return $data;
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
        if (empty($limit)){
            $limit = $this->searchIndex->getAllDocumentsCount() - $offset;
        }
        $json = <<<EOT
            {
               "from": $offset,
               "size": $limit,
               "sort" : [
                    { "id" : "asc" }
                ],
                "query": {
                    "match_all": {}
                }
            }
EOT;
        return $this->searchIndex->search($json, $options);
    }


    /**
     *   Get the number of documents.
     *
     *   @return integer|null Number of documents or null if errors occurred.
     */
    public function getAllDocumentsCount()
    {
        $json = <<<'EOT'
            {
                "query": {
                    "match_all": {}
                }
            }
EOT;
        return $this->searchIndex->count($json);
    }

}
