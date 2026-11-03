<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit\Auth;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Ocolin\Billmax\Auth\AuthUserPass;
use Ocolin\Billmax\Auth\TokenInterface;
use Ocolin\Billmax\Config;
use Ocolin\Billmax\Exceptions\ApiException;
use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Exceptions\ConnectionException;
use PHPUnit\Framework\TestCase;
use Throwable;

class AuthSessionLoginTest extends TestCase
{

    /* HELPER
    ----------------------------------------------------------------------------- */

    private function makeAuth( GuzzleResponse|Throwable $queued ): AuthUserPass
    {
        $client = new GuzzleClient([
            'handler' => HandlerStack::create( new MockHandler( [ $queued ] ) )
        ]);

        $cache = $this->createStub( TokenInterface::class );
        $cache->method( 'get' )->willReturn( null );

        return new AuthUserPass(
            config: new Config(
                host:     'https://billmax.example.com:3100',
                auth:     'USERPASS',
                apiKey:   'abc123',
                username: 'john',
                password: 'secret'
            ),
            cache:  $cache,
            client: $client
        );
    }


    /* TESTS
    ----------------------------------------------------------------------------- */

    public function testConnectFailureThrowsConnectionException(): void
    {
        $original = new ConnectException(
            'Connection refused', new Request( 'POST', '/login' )
        );
        $auth = $this->makeAuth( $original );

        try {
            $auth->getHeaders();
            $this->fail( 'Expected ConnectionException was not thrown.' );
        }
        catch( ConnectionException $e ) {
            $this->assertSame( $original, $e->getPrevious() );
        }
    }

    public function testRejectedLoginThrowsAuthException(): void
    {
        $this->expectException( AuthException::class );

        $this->makeAuth( new GuzzleResponse( status: 401, body: '{"errors":[]}' ) )
            ->getHeaders();
    }

    public function testBadRequestLoginThrowsAuthException(): void
    {
        $this->expectException( AuthException::class );

        $this->makeAuth( new GuzzleResponse( status: 400, body: '{"errors":[]}' ) )
            ->getHeaders();
    }

    public function testOtherClientErrorThrowsApiExceptionNotAuth(): void
    {
        $auth = $this->makeAuth( new GuzzleResponse( status: 429, body: '{}' ) );

        try {
            $auth->getHeaders();
            $this->fail( 'Expected ApiException was not thrown.' );
        }
        catch( ApiException $e ) {
            $this->assertNotInstanceOf( AuthException::class, $e );
        }
    }

    public function testServerErrorThrowsApiException(): void
    {
        $this->expectException( ApiException::class );

        $this->makeAuth( new GuzzleResponse( status: 500, body: '{}' ) )
            ->getHeaders();
    }
}