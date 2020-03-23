# elasticsearch
Tool for full text search using Elasticsearch.

The SearchIndex class (PHP) implements functionality for full-text search using Elasticsearch, creating an index, adding and updating documents.
Tool allows you to create a set of documents in one request to the Elasticsearch REST API using Bulk API.

Simple example of full-text search:

    $index_name = 'articles';
    $host = 'http://localhost:9200';
    $searchIndex = new SearchIndex($index_name, $host);
    $keywords = 'book'; // keywords
    $field_name = 'content';
    $search_results = $searchIndex->searchSimple($keywords, $field_name);
    var_dump($search_results);


Example of full-text search using Query DSL:

    $index_name = 'articles';
    $host = 'http://localhost:9200';
    $searchIndex = new SearchIndex($index_name, $host);
    $query = [
        "query"  => [
            "match" => [
                "content" => 'book',
            ],
        ],
        "size" => 100,
        "sort": [
            "rating": [
                "order": "desc"
            ]
        ],
        "_source": [
            "id", "title", "rating", "content"
        ]    
    ]; // DSL query
    $search_results = $searchIndex->search($query);
    var_dump($search_results);


Example of adding a document to the index:

    $index_name = 'articles';
    $host = 'http://localhost:9200';
    $searchIndex = new SearchIndex($index_name, $host);
    $document = [
        'id' => 1,
        'title' => 'doc1',
        'content' => 'this is a text'
    ];
    $searchIndex->createOrUpdateDocument($document);


Text analysis example for receiving tokens:

    $index_name = 'articles';
    $host = 'http://localhost:9200';
    $searchIndex = new SearchIndex($index_name, $host);

    $results = $searchIndex->analyze([
       "analyzer" => "standard",
       "text" => "this is a text, logs articles indexes"
    ]);
    var_dump($results);