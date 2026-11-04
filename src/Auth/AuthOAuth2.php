<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Auth;

use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Config;

class AuthOAuth2 implements AuthInterface
{

/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    /**
     * @param Config $config
     */
    public function __construct( protected Config $config ) {}



/* GET HTTP HEADERS
----------------------------------------------------------------------------- */

    /**
     * @return array<string, int|float|string> List of headers.
     * @throws AuthException
     */
    public function getHeaders() : array
    {
        $temp = $this->config;
        throw new AuthException( message: "Auth OAuth2 not implemented" );
    }



/* LOGIN
----------------------------------------------------------------------------- */

    /**
     * @return never
     * @throws AuthException
     */
    public function login() : never
    {
        throw new AuthException(
            message: 'AUTH OAUTH2 Possible bad Client ID or Secret.'
        );
    }



/* RE-AUTHENTICATE
----------------------------------------------------------------------------- */

    /**
     * @return never
     * @throws AuthException
     */
    public function reAuthenticate() : never
    {
        throw new AuthException(
            message: 'AUTH OAUTH2 Possible bad Client ID or Secret.'
        );
    }
}