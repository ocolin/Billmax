<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Auth;

use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Config;

class AuthOAuth2 implements AuthInterface
{

/*
----------------------------------------------------------------------------- */

    public function __construct( protected Config $config ) {}


/*
----------------------------------------------------------------------------- */

    public function getHeaders() : array
    {
        $temp = $this->config;
        throw new AuthException( message: "Auth OAuth2 not implemented" );
    }


/*
----------------------------------------------------------------------------- */

    public function login() : never
    {
        throw new AuthException(
            message: 'AUTH OAUTH2 Possible bad Client ID or Secret.'
        );
    }



/*
----------------------------------------------------------------------------- */

    public function reAuthenticate() : never
    {
        throw new AuthException(
            message: 'AUTH OAUTH2 Possible bad Client ID or Secret.'
        );
    }
}