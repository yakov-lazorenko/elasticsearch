<?php

namespace App\Services\Search;

use GuzzleHttp;


/**
 *   Class DocumentWriter.
 *   Tool for creating, updating and deleting documents in Elasticsearch index.
 */
class DocumentWriter
{
    /**
     *   @var SearchIndex
     */
    protected $searchIndex;


    /**
     *   Create a new DocumentWriter class.
     *
     *   @param SearchIndex $searchIndex
     *   @return DocumentWriter
     */
    public function __construct($searchIndex)
    {
        $this->searchIndex = $searchIndex;
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
        $url = $this->searchIndex->getHost() . '/'
            . $this->searchIndex->getIndexName() . '/'
            . '_doc/' . $document['id'] . '?pretty';

        $client = new GuzzleHttp\Client();

        try {
            $response = $client->request(
                'PUT',
                $url,
                [
                    'json' => $document,
                    'timeout' => $this->searchIndex->getRequestTimeout()
                ]
            );
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return false;
        }

        if (empty($response) || !in_array($response->getStatusCode(), [200,201]) || empty($response->getBody())) {
            return false;
        }
        $data = \json_decode($response->getBody(), true);
        if (!$data || empty($data['result']) || !in_array($data['result'], ['created', 'updated'])) {
            $this->searchIndex->setError('Error in DocumentWriter::createOrUpdateDocument()');
            return false;
        }
        return true;
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
        $url = $this->searchIndex->getHost() . '/_bulk?pretty';
        $client = new GuzzleHttp\Client();

        try {
            $response = $client->request(
                'POST',
                $url,
                [
                    'headers' => ['Content-Type' => 'application/x-ndjson'],
                    'body' => $this->getRequestBodyToCreateDocuments($documents),
                    'timeout' => $this->searchIndex->getRequestTimeout()
                ]
            );
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return false;
        }

        if (empty($response) || !in_array($response->getStatusCode(), [200,201]) || empty($response->getBody())) {
            return false;
        }
        $data = \json_decode($response->getBody(), true);
        if (!$data || !isset($data['errors']) || $data['errors']) {
            $this->searchIndex->setError('Error in DocumentWriter::createDocuments()');
            return false;
        }
        return true;
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
        $url = $this->searchIndex->getHost() . '/'
            . $this->searchIndex->getIndexName() . '/'
            . '_doc/' . $id . '?pretty';

        $client = new GuzzleHttp\Client();

        try {
            $response = $client->request('DELETE', $url, ['timeout' => $this->searchIndex->getRequestTimeout()]);
        } catch (\Throwable $e) {
            $this->searchIndex->setError($e->getMessage());
            return false;
        }

        if (empty($response) || $response->getStatusCode() != 200 || empty($response->getBody())) {
            return false;
        }
        $data = \json_decode($response->getBody(), true);
        if (!$data || empty($data['result']) || ($data['result'] != 'deleted')) {
            $this->searchIndex->setError('Error in DocumentWriter::deleteDocument()');
            return false;
        }

        return true;
    }


    public function getRequestBodyToCreateDocuments($documents)
    {
        $request_body = '';
        foreach ($documents as $document)
        {
            $action = json_encode([
                'index' => [
                    "_index" => $this->searchIndex->getIndexName(),
                    "_id" => $document['id']
                ],
            ]);
            $data = json_encode($document);
            $request_body .= $action . "\n" . $data . "\n";
        }
        return $request_body;
    }

}
