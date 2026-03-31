<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Auth;

use GuzzleHttp\Exception\GuzzleException;
use Ocolin\Billmax\Config;
use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Exceptions\CacheException;
use GuzzleHttp\ClientInterface;

class AuthUserPass extends AuthSession
{


/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    public function __construct(
                  Config $config,
         ?TokenInterface $cache  = null,
        ?ClientInterface $client = null
    )
    {
        $hash = md5( string: $config->host . $config->username );
        parent::__construct(
            config: $config, tokenManager: $cache, hash: $hash, client: $client
        );
    }



/* GET HEADERS
----------------------------------------------------------------------------- */

    /**
     * @return array<string, string> Array of auth headers.
     * @throws GuzzleException
     * @throws AuthException
     * @throws CacheException
     */
    public function getHeaders() : array
    {
        $token = $this->tokenManager->get();
        if( $token === null ) {
            $token = $this->login( identifier: (string)$this->config->username );
            $this->tokenManager->set( token: $token );
        }

        return [
            'api_key'       => (string) $this->config->apiKey,
            'Authorization' => "Bearer {$token}"
        ];
    }



/* RE-AUTHENTICATION
----------------------------------------------------------------------------- */

    /**
     * @return array<string, string> Array of Auth headers.
     * @throws AuthException
     * @throws CacheException
     * @throws GuzzleException
     */
    public function reAuthenticate(): array
    {
        $token = $this->login( identifier: (string) $this->config->username );
        $this->tokenManager->set( token: $token );
        return $this->getHeaders();
    }
}