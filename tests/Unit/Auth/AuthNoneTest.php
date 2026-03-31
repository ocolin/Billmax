<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit\Auth;

use Ocolin\Billmax\Auth\AuthNone;
use Ocolin\Billmax\Config;
use Ocolin\Billmax\Exceptions\AuthException;
use PHPUnit\Framework\TestCase;

class AuthNoneTest extends TestCase
{

/* SETUP
----------------------------------------------------------------------------- */

    private AuthNone $auth;

    protected function setUp(): void
    {
        $this->auth = new AuthNone(
            config: new Config(
                  host: 'https://billmax.example.com:3100',
                  auth: 'NONE',
                apiKey: 'abc123'
            )
        );
    }


/* TESTS
----------------------------------------------------------------------------- */

    public function testGetHeadersReturnsApiKey(): void
    {
        $headers = $this->auth->getHeaders();
        $this->assertSame( 'abc123', $headers['api_key'] );
    }


    public function testReAuthenticateThrowsAuthException(): void
    {
        $this->expectException( AuthException::class );
        $this->expectExceptionMessage( 'AUTH NONE: Re-authentication not supported. Check your API key.' );
        $this->auth->reAuthenticate();
    }
}