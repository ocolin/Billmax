<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Ocolin\Billmax\Auth\TokenInterface;
use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Exceptions\CacheException;
use Ocolin\Billmax\Exceptions\ApiException;

class Billmax
{
    /**
     * @var Config Configuration data.
     */
    private Config $config;

    /**
     * @var HTTP HTTP handler.
     */
    private HTTP $http;


/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    /**
     * @param ?Config $config Configuration data.
     * @param ?HTTP $http HTTP handler for mocking.
     * @param ?TokenInterface $cache Token interface for mocking.
     * @param ?ClientInterface $guzzle Guzzle client for mocking.
     * @throws AuthException|CacheException
     */
    public function __construct(
                     ?Config $config = null,
                       ?HTTP $http   = null,
             ?TokenInterface $cache  = null,
            ?ClientInterface $guzzle = null
    )
    {
        $this->config = $config ?? new Config();
        $this->http   = $http   ?? new HTTP(
              config: $this->config,
              client: $guzzle,
               cache: $cache
        );
    }



/* SEND GET REQUEST TO SERVER
----------------------------------------------------------------------------- */

    /**
     * @param string $endpoint API end point URI.
     * @param array<string, string|int|float|bool>|object $query GET and Path parameters.
     * @return Response Api client response object.
     * @throws AuthException|CacheException|ApiException|GuzzleException
     */
    public function get( string $endpoint, array|object $query = [] ) : Response
    {
        return $this->http->request( path: $endpoint, query: (array)$query );
    }



/* SEND DELETE REQUEST TO SERVER
----------------------------------------------------------------------------- */

    /**
     * @param string $endpoint API end point URI.
     * @param array<string, string|int|float|bool>|object $query GET and Path parameters.
     * @return Response Api client response object.
     * @throws ApiException|AuthException|CacheException|GuzzleException
     */
    public function delete( string $endpoint, array|object $query = [] ) : Response
    {
        return $this->http->request(
            path: $endpoint, method: 'DELETE', query: (array)$query
        );
    }



/* SEND POST REQUEST TO SERVER
----------------------------------------------------------------------------- */

    /**
     * @param string $endpoint API end point URI.
     * @param array<string, string|int|float|bool>|object $query GET and Path parameters.
     * @param array<string, mixed>|object $body HTTP request POST body.
     * @return Response Api client response object.
     * @throws ApiException|AuthException|CacheException|GuzzleException
     */
    public function post(
              string $endpoint,
        array|object $query = [],
        array|object $body  = [],
    ) : Response
    {
        return $this->http->request(
            path: $endpoint, method: 'POST', query: (array)$query, body: $body
        );
    }



/* SEND PATCH REQUEST TO SERVER
----------------------------------------------------------------------------- */

    /**
     * @param string $endpoint API end point URI.
     * @param array<string, string|int|float|bool>|object $query GET and Path parameters.
     * @param array<string, mixed>|object $body HTTP request POST body.
     * @param bool $autoGet Update generation value before using PATCH.
     * @return Response Api client response object.
     * @throws ApiException|AuthException|CacheException|GuzzleException
     */
    public function patch(
              string $endpoint,
        array|object $query   = [],
        array|object $body    = [],
                bool $autoGet = true
    ) : Response
    {
        return $this->http->request(
               path: $endpoint,
             method: 'PATCH',
              query: (array)$query,
               body: $body,
            autoGet: $autoGet
        );
    }



/* SEND HTTP REQUEST TO SERVER
----------------------------------------------------------------------------- */

    /**
     * @param string $endpoint API end point URI.
     * @param string $method HTTP method.
     * @param array<string, string|int|float|bool>|object $query GET and Path parameters.
     * @param array<string, mixed>|object $body HTTP request POST body.
     * @param bool $autoGet Update generation value before using PATCH.
     * @return Response Api client response object.
     * @throws ApiException|AuthException|CacheException|GuzzleException
     */
    public function request(
              string $endpoint,
              string $method  = 'GET',
        array|object $query   = [],
        array|object $body    = [],
                bool $autoGet = true
    ) : Response
    {
        return $this->http->request(
               path: $endpoint,
             method: $method,
              query: (array)$query,
               body: $body,
            autoGet: $autoGet
        );
    }


/* UPLOAD
----------------------------------------------------------------------------- */

    /**
     * @param string $entity Entity type for file (Account, Ticket, etc).
     * @param int $entityId Entity ID (account number, ticket number, etc).
     * @param array<int, array{path: string, class: string, description: string}> $filePaths
     *  List of files in format [ path, fileClass, description.
     * @return Response Client HTTP response object.
     * @throws ApiException|AuthException|CacheException|GuzzleException
     */
    public function upload(
        string $entity,
           int $entityId,
         array $filePaths
    ) : Response
    {
        return $this->http->upload(
               entity: $entity,
             entityId: $entityId,
            filePaths: $filePaths
        );
    }
}