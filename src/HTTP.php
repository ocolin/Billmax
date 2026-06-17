<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Ocolin\Billmax\Auth\AuthInterface;
use Ocolin\Billmax\Auth\AuthNone;
use Ocolin\Billmax\Auth\AuthUserPass;
use Ocolin\Billmax\Auth\AuthOAuth2;
use Ocolin\Billmax\Exceptions\ApiException;
use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Exceptions\CacheException;
use Ocolin\Billmax\Auth\TokenInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client as GuzzleClient;


class HTTP
{
    /**
     * @var Config Configuration data.
     */
    private Config $config;

    /**
     * @var ClientInterface Guzzle HTTP client.
     */
    private ClientInterface $client;

    /**
     * @var AuthInterface Authorization client.
     */
    private AuthInterface $auth;

    private const array VALID_METHODS = [
        'GET',
        'POST',
        'PATCH',
        'DELETE',
    ];


/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    /**
     * @param Config $config Configuration data.
     * @param ?ClientInterface $client Guzzle HTTP client.
     * @param ?TokenInterface $cache Token client.
     * @throws AuthException|CacheException
     */
    public function __construct(
                     Config $config,
           ?ClientInterface $client = null,
            ?TokenInterface $cache  = null
    )
    {
        $this->config = $config;
        $this->client = $client ?? new GuzzleClient([
            'base_uri' => rtrim(
                string: (string)$this->config->host, characters: '/'
            ) . '/',
            'http_errors' => false,
            'headers'     => array_filter([
                'Accept'        => 'application/json; charset=utf-8',
                'X-App-Id'      => $this->config->appId,
                'X-Device-Id'   => $this->config->deviceId,
                'X-App-Version' => $this->config->appVersion,
            ]),
        ]);

        $this->auth = match( $this->config->auth )
        {
            'NONE'      => new AuthNone(  config: $this->config ),
            'USERPASS'  => new AuthUserPass(
                config: $this->config, cache: $cache, client: $client
            ),
            'OAUTH2'    => new AuthOAuth2( config: $this->config ),
            default     => throw new AuthException( message: 'Unknown auth type' ),
        };
    }



/* API REQUEST
----------------------------------------------------------------------------- */

    /**
     * Make an API request.
     *
     * @param string $path HTTP URI path.
     * @param string $method HTTP method.
     * @param array<string, string|int|float|bool> $query HTTP query parameters.
     * @param array<string, mixed>|object $body HTTP POST parameters.
     * @param bool $autoGet Automatically get generation for PATCH method.
     * @return Response API client response object.
     * @throws ApiException|AuthException|CacheException|GuzzleException
     */
    public function request(
              string $path,
              string $method  = 'GET',
               array $query   = [],
        array|object $body    = [],
                bool $autoGet = true
    ) : Response
    {
        $body   = (array)$body;
        $method = strtoupper( string: $method );

        if( !in_array(
            needle: $method, haystack: self::VALID_METHODS, strict: true )
        ) {
            throw new ApiException(  message: "Invalid HTTP method: {$method}" );
        }

        if( $method === 'PATCH' AND $autoGet === true ) {
            $body['generation'] = $this->fetchGeneration(
                path: $path, query: $query
            );
        }

        $response = $this->send(
              path: $path,
            method: $method,
             query: $query,
              body: $body,
        );

        if( $response->status === 401 ) { $this->auth->reAuthenticate(); }
        else { return $response; }

        $response =  $this->send(
              path: $path,
            method: $method,
             query: $query,
              body: $body,
        );
        if( $response->status === 401 ) { throw new AuthException(
            message: "AUTH: Not authorized to make API call. Check credentials."
        );}

        return $response;
    }



/* FETCH GENERATION VALUE FOR OBJECT
----------------------------------------------------------------------------- */

    /**
     * Fetch the generation date/time from an object.
     *
     * @param string $path URI path on API server.
     * @param array<string, string|int|float|bool> $query HTTP query parameters.
     * @return string Object generation value.
     * @throws ApiException|AuthException|CacheException|GuzzleException
     */
    private function fetchGeneration( string $path, array $query ) : string
    {
        $get = $this->send( path: $path, query: $query );

        if( !self::hasValidGeneration( body: $get->body )) {
            throw new ApiException( message: 'No generation found.' );
        }

        // @phpstan-ignore-next-line
        return $get->body[0]->generation;
    }



/* SEND HTTP REQUEST
----------------------------------------------------------------------------- */

    /**
     * Send HTTP Request to Billmax.
     *
     * @param string $path HTTP URI path.
     * @param string $method HTTP method.
     * @param array<string, string|int|float|bool> $query HTTP query parameters.
     * @param array<string, mixed> $body HTTP POST body.
     * @return Response API client response object.
     * @throws ApiException|CacheException|AuthException|GuzzleException
     */
    private function send(
        string $path,
        string $method = 'GET',
         array $query  = [],
         array $body   = [],
    ) : Response
    {
        $path = self::buildPath( path: $path, query: $query );

        return self::buildResponse( response: $this->client->request(
             method: $method,
                uri: $path,
            options: array_merge(
                [ 'verify'  => false, 'timeout' => 30 ],
                $this->config->options,
                [
                    'query'   => $query,
                    'json'    => in_array( $method, ['POST', 'PATCH'] )
                        ? $body : null,
                    'headers' => $this->auth->getHeaders(),
                ]
            )
        ));
    }



/* UPLOAD
----------------------------------------------------------------------------- */

    /**
     * Upload files to Billmax.
     *
     * @param string $entity Entity type for file (Account, Ticket, etc).
     * @param int $entityId Entity ID (account number, ticket number, etc).
     * @param array<int, array{path: string, class: string, description: string}> $filePaths
     *  List of files in format [ path, fileClass, description.
     * @return Response Client HTTP response object/
     * @throws ApiException|AuthException|CacheException|GuzzleException
     */
    public function upload(
        string $entity,
           int $entityId,
         array $filePaths
    ) : Response
    {
        $multi = [
            [ 'name' => 'entity',   'contents' => $entity ],
            [ 'name' => 'entityId', 'contents' => $entityId ],
        ];
        $count = 0;
        foreach( $filePaths as $filePath )
        {
            if( !empty($filePath['path']) AND !empty($filePath['description']) ) {

                $resource = fopen( filename: $filePath['path'], mode: 'r' );
                if( $resource === false ) {
                    throw new ApiException( message: "Cannot open file: {$filePath['path']}" );
                }
                $class = empty( $filePath['class']) ? 'support' : $filePath['class'];

                $multi[] = [ 'name' => 'fileClass'   . $count, 'contents' => $class ];
                $multi[] = [ 'name' => 'description' . $count, 'contents' => $filePath['description'] ];
                $multi[] = [
                    'name'     => 'files',
                    'contents' => $resource,
                    'filename' => basename( $filePath['path'] ),
                ];

                $count++;
            }
        }

        return self::buildResponse( response: $this->client->request(
             method: 'POST',
                uri: 'files',
            options:
             array_merge(
                 [ 'verify' => false, 'timeout' => 20 ],
                 $this->config->options,
                 [
                     'multipart' => $multi,
                     'headers'   => $this->auth->getHeaders(),
                ]
             )
        ));
    }



/* BUILD URI PATH
----------------------------------------------------------------------------- */

    /**
     * Replaces any variable tokens in URI path and replaces with values.
     *
     * @param string $path HTTP URI path.
     * @param array<string, string|int|float|bool> $query HTTP query and path
            parameters.
     * @return string Interpolated URI path.
     */
    private static function buildPath( string $path, array &$query ): string
    {
        $path = ltrim( string: $path, characters: '/' );
        if( !str_contains( haystack: $path, needle: '{' )) {
            return $path;
        }

        foreach( $query as $key => $value )
        {
            if( str_contains( $path, "{{$key}}" )) {
                $path = str_replace(
                    search: "{{$key}}", replace: (string)$value, subject: $path
                );
                unset( $query[$key] );
            }
        }

        return $path;
    }



/* BUILD RESPONSE OBJECT
----------------------------------------------------------------------------- */

    /**
     * @param ResponseInterface $response Guzzle HTTP response object.
     * @return Response API client HTTP response object.
     * @throws ApiException
     */
    private static function buildResponse( ResponseInterface $response ): Response
    {
        $status = $response->getStatusCode();
        $body = null;

        if( $status !== 204 ) {
            $body = json_decode( $response->getBody()->getContents());
            if( !is_object( $body ) AND !is_array( $body )) {
                throw new ApiException(
                    message: "API: Unexpected response format. " . gettype( $body )
                );
            }
        }

        return new Response(
            status:        $status,
            statusMessage: $response->getReasonPhrase(),
            headers:       $response->getHeaders(),
            body:          $body
        );
    }



/* VALIDATE HTTP BODY HAS A GENERATION VALUE
----------------------------------------------------------------------------- */

    /**
     * @param mixed$body HTTP response body.
     * @return bool Valid generation.
     */
    private static function hasValidGeneration( mixed $body ) : bool
    {
        return (
            is_array( $body )    AND
              !empty( $body )    AND
           is_object( $body[0] ) AND
               isset( $body[0]->generation ) AND
           is_string( $body[0]->generation ) AND
              !empty( $body[0]->generation )
        );
    }
}