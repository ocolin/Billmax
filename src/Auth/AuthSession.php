<?php

declare( strict_types = 1);

namespace Ocolin\Billmax\Auth;

use GuzzleHttp\Client AS GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Ocolin\Billmax\Config;
use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Exceptions\CacheException;

abstract class AuthSession implements AuthInterface
{
    protected TokenInterface $tokenManager;

    protected GuzzleClientInterface $client;

/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    /**
     * @param Config $config Configuration settings.
     * @param TokenInterface|null $tokenManager Manager for auth tokens.
     * @param string $hash Hash for storage name.
     * @param ?GuzzleClientInterface $client Guzzle interface for mocking.
     * @throws CacheException
     */
    public function __construct(
        protected Config $config,
        ?TokenInterface $tokenManager = null,
        string $hash = '',
        ?GuzzleClientInterface $client = null
    ) {
        $this->client = $client ?? new GuzzleClient();
        $this->tokenManager = $tokenManager ?? new FileTokenCache( hash: $hash );
    }


/* LOGIN TO BILLMAX SERVER
----------------------------------------------------------------------------- */

    /**
     * @param string $identifier Which identifier to use for login username.
     * @return string Session ID token.
     * @throws AuthException
     * @throws GuzzleException
     */
    public function login( string $identifier ) : string
    {
        $host = rtrim( string: (string)$this->config->host, characters: '/' )
            . '/login';

        $response = $this->client->request(
             method: 'POST',
                uri: $host,
            options: array_merge(
                ['verify' => false, 'timeout' => 30],
                $this->config->options ?? [],
                [
                    'headers' => array_filter([
                        'api_key'       => (string)$this->config->apiKey,
                        'X-App-Id'      => $this->config->appId,
                        'X-Device-Id'   => $this->config->deviceId,
                        'X-App-Version' => $this->config->appVersion,
                    ]),
                    'json'    => [
                        'username' => $identifier,
                        'password' => $this->config->password
                ]]
            )
        );

        $status = $response->getStatusCode();
        $body = json_decode( $response->getBody()->getContents());
        if(
            $status === 200 AND
            is_object( $body ) AND
            !empty( $body->BXSESSIONID )
        ) {
            return $body->BXSESSIONID;
        }

        throw new AuthException( message: "AUTH: Login failed." );
    }
}