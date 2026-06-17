<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax;

use Ocolin\GlobalType\ENV;
use Ocolin\Billmax\Exceptions\ConfigException;

readonly class Config
{
    /**
     * @var ?string Hostname of Billmax server.
     */
    public ?string $host;

    /**
     * @var ?string Authentication type used by Billmax.
     */
    public ?string $auth;

    /**
     * @var ?string API key of Billmax server.
     */
    public ?string $apiKey;

    /**
     * @var ?string Username to login as.
     */
    public ?string $username;

    /**
     * @var ?string Password to log in with.
     */
    public ?string $password;

    /**
     * @var ?string Oauth2 Client ID.
     */
    public ?string $clientId;

    /**
     * @var ?string Oauth 2 Client secret.
     */
    public ?string $clientSecret;

    /**
     * @var ?string Application client ID from Billmax.
     */
    public ?string $appId;

    /**
     * @var ?string Optional identifier for remote device using API.
     */
    public ?string $deviceId;

    /**
     * @var ?string Optional version value for API device.
     */
    public ?string $appVersion;

    /**
     * @var array<string, string|int|float|bool> List of Guzzle options.
     */
    public array $options;


/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    /**
     * @param ?string $host Hostname of Billmax server.
     * @param ?string $auth Authentication type used by Billmax.
     * @param ?string $apiKey API key of Billmax server.
     * @param ?string $username Username to login as.
     * @param ?string $password Password to log in with.
     * @param ?string $clientId Oauth2 Client ID.
     * @param ?string $clientSecret Oauth 2 Client secret.
     * @param array<string, string|int|float|bool> $options
     * @throws ConfigException
     */
    public function __construct(
        ?string $host         = null,
        ?string $auth         = null,
        ?string $apiKey       = null,
        ?string $username     = null,
        ?string $password     = null,
        ?string $clientId     = null,
        ?string $clientSecret = null,
        ?string $appId        = null,
        ?string $deviceId     = null,
        ?string $appVersion   = null,
          array $options      = []
    ) {
        $this->host       = $host       ?? ENV::getStringNull( name: 'BILLMAX_API_HOST' );
        $this->auth       = $auth       ?? ENV::getStringNull( name: 'BILLMAX_API_AUTH' );
        $this->apiKey     = $apiKey     ?? ENV::getStringNull( name: 'BILLMAX_API_KEY' );
        $this->username   = $username   ?? ENV::getStringNull( name: 'BILLMAX_API_USERNAME' );
        $this->password   = $password   ?? ENV::getStringNull( name: 'BILLMAX_API_PASSWORD' );
        $this->appId      = $appId      ?? ENV::getStringNull( name: 'BILLMAX_API_APP_ID' ) ?? 'billmax-techapp';
        $this->deviceId   = $deviceId   ?? ENV::getStringNull( name: 'BILLMAX_API_DEVICE_ID' );
        $this->appVersion = $appVersion ?? ENV::getStringNull( name: 'BILLMAX_API_APP_VERSION' );

        $this->options = $options;

        $this->clientId = $clientId ?? ENV::getStringNull(
            name: 'BILLMAX_API_OAUTH2_CLIENT_ID'
        );
        $this->clientSecret = $clientSecret ?? ENV::getStringNull(
            name: 'BILLMAX_API_OAUTH2_CLIENT_SECRET'
        );

        $this->validateHost();
        $this->validateAuth();

        match( $this->auth ) {
            'NONE'      => $this->validateNone(),
            'USERPASS'  => $this->validateUserPass(),
            'OAUTH2'    => $this->validateOAuth2(),
            default     => null
        };
    }


/* VALIDATE HOST
----------------------------------------------------------------------------- */

    /**
     * Check that Billmax server hostname has been provided.
     *
     * @return void
     * @throws ConfigException
     */
    private function validateHost() : void
    {
        if(  $this->host === null ) {
            throw new ConfigException( message: "Missing Billmax host name." );
        }
    }


/* VALIDATE AUTH METHOD
----------------------------------------------------------------------------- */

    /**
     * Check that an API authentication method is specified.
     *
     * @return void
     * @throws ConfigException
     */

    private function validateAuth() : void
    {
        $auths = [ 'NONE', 'USERPASS', 'EMAILPASS', 'OAUTH2' ];

        if( in_array( needle: $this->auth, haystack: $auths ) === false )
        {
            throw new ConfigException(
                message: "AUTH: Missing Authentication method."
            );
        }
    }


/* VALIDATE NONE
----------------------------------------------------------------------------- */

    /**
     * Validation for authentication method NONE.
     *
     * @return void
     * @throws ConfigException
     */
    private function validateNone() : void
    {
        if( $this->apiKey === null ) {
            throw new ConfigException( message: "AUTH NONE: Missing API Key." );
        }
    }


/* VALIDATE USERPASS
----------------------------------------------------------------------------- */

    /**
     * Check that USERPASS method has a username and password.
     *
     * @return void
     * @throws ConfigException
     */
    private function validateUserPass() : void
    {
        if( $this->username === null ) {
            throw new ConfigException(
                message: "AUTH USERPASS: Missing Auth username."
            );
        }

        if( $this->password === null ) {
            throw new ConfigException(
                message: "AUTH USERPASS: Missing Auth password."
            );
        }
    }



/* VALIDATE OAUTH2
----------------------------------------------------------------------------- */

    /**
     * Check that OAUTH2 has a client ID and client secret.
     * @return void
     * @throws ConfigException
     */
    private function validateOAuth2() : void
    {
        if( $this->clientId === null ) {
            throw new ConfigException(
                message: "AUTH OAUTH2: Missing Auth client ID."
            );
        }

        if( $this->clientSecret === null ) {
            throw new ConfigException(
                message: "AUTH OAUTH2: Missing Auth client secret."
            );
        }
    }
}