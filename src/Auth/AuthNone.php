<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Auth;

use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Config;

readonly class AuthNone implements AuthInterface
{

/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    /**
     * @param Config $config Configuration data.
     */
    public function __construct( private Config $config ) {}


/* GET AUTH HEADERS
----------------------------------------------------------------------------- */

    /**
     * @return array<string, string > HTTP request headers.
     */
    public function getHeaders() : array
    {
        return [
            'api_key' => (string)$this->config->apiKey
        ];
    }



/* RE-AUTHENTICATION NOT SUPPORTED
----------------------------------------------------------------------------- */

    /**
     * Re-authenticate not supported.
     *
     * @return never
     * @throws AuthException
     */
    public function reAuthenticate(): never
    {
        throw new AuthException( message:
            'AUTH NONE: Re-authentication not supported. Check your API key.'
        );
    }

}