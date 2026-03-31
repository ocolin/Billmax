<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax;

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
            'headers'     => [
                'Accept'  => 'application/json; charset=utf-8',
            ],
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
     * @param string $path HTTP URI path.
     * @param string $method HTTP method.
     * @param array<string, string|int|float|bool> $query HTTP query parameters.
     * @param array<string, mixed>|object $body HTTP POST parameters.
     * @param bool $autoGet Automatically get generation for PATCH method.
     * @return Response API client response object.
     * @throws ApiException|AuthException|CacheException
     * @throws GuzzleException
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