<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit\Auth;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Ocolin\Billmax\Auth\AuthUserPass;
use Ocolin\Billmax\Auth\TokenInterface;
use Ocolin\Billmax\Config;
use Ocolin\Billmax\Exceptions\AuthException;
use PHPUnit\Framework\TestCase;

class AuthUserPassTest extends TestCase
{
    private Config $config;
    private ClientInterface $mockClient;
    private TokenInterface $mockCache;


/* SETUP
----------------------------------------------------------------------------- */

    protected function setUp(): void
    {
        $this->config = new Config(
            host:     'https://billmax.example.com:3100',
            auth:     'USERPASS',
            apiKey:   'abc123',
            username: 'john',
            password: 'secret'
        );

        $this->mockClient = $this->createStub( ClientInterface::class );
        $this->mockCache  = $this->createStub( TokenInterface::class );
    }


/* TESTS
----------------------------------------------------------------------------- */

    public function testGetHeadersUsesStoredToken(): void
    {
        $this->mockCache
            ->method( 'get' )
            ->willReturn( 'stored_token' );

        $auth = new AuthUserPass(
            config: $this->config,
            cache:  $this->mockCache,
            client: $this->mockClient
        );

        $headers = $auth->getHeaders();

        $this->assertSame( 'Bearer stored_token', $headers['Authorization'] );
        $this->assertSame( 'abc123', $headers['api_key'] );
    }


    public function testGetHeadersLogsInWhenNoToken(): void
    {
        $mockCache = $this->createMock( TokenInterface::class );
        $mockCache
            ->method( 'get' )
            ->willReturn( null );

        $mockCache
            ->expects( $this->once() )
            ->method( 'set' );

        $this->mockClient
            ->method( 'request' )
            ->willReturn( new Response(
                status: 200,
                  body: json_encode([ 'BXSESSIONID' => 'new_token' ])
            ));

        $auth = new AuthUserPass(
            config: $this->config,
             cache: $mockCache,
            client: $this->mockClient
        );

        $headers = $auth->getHeaders();

        $this->assertSame( 'Bearer new_token', $headers['Authorization'] );
    }

    public function testGetHeadersThrowsOnFailedLogin(): void
    {
        $this->mockCache
            ->method( 'get' )
            ->willReturn( null );

        $this->mockClient
            ->method( 'request' )
            ->willReturn( new Response(
                status: 401,
                body: json_encode([ 'error' => 'invalid credentials' ])
            ));

        $this->expectException( AuthException::class );

        new AuthUserPass(
            config: $this->config,
            cache:  $this->mockCache,
            client: $this->mockClient
        );

        (new AuthUserPass(
            config: $this->config,
            cache:  $this->mockCache,
            client: $this->mockClient
        ))->getHeaders();
    }

    public function testReAuthenticateReturnsFreshHeaders(): void
    {
        $mockCache = $this->createMock( TokenInterface::class );
        $mockCache
            ->method( 'get' )
            ->willReturn( 'fresh_token' );

        $mockCache
            ->expects( $this->once() )->method( 'set' );

        $this->mockClient
            ->method( 'request' )
            ->willReturn( new Response(
                status: 200,
                body: json_encode([ 'BXSESSIONID' => 'fresh_token' ])
            ));

        $auth = new AuthUserPass(
            config: $this->config,
             cache: $mockCache,
            client: $this->mockClient
        );

        $headers = $auth->reAuthenticate();

        $this->assertSame( 'Bearer fresh_token', $headers['Authorization'] );
    }

    public function testReAuthenticateThrowsOnFailedLogin(): void
    {
        $this->mockCache
            ->method( 'get' )
            ->willReturn( 'old_token' );

        $this->mockClient
            ->method( 'request' )
            ->willReturn( new Response(
                status: 401,
                body: json_encode([ 'error' => 'invalid credentials' ])
            ));

        $this->expectException( AuthException::class );

        $auth = new AuthUserPass(
            config: $this->config,
            cache:  $this->mockCache,
            client: $this->mockClient
        );

        $auth->reAuthenticate();
    }

}