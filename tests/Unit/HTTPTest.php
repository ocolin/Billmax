<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Ocolin\Billmax\Auth\AuthInterface;
use Ocolin\Billmax\Auth\TokenInterface;
use Ocolin\Billmax\Config;
use Ocolin\Billmax\Exceptions\ApiException;
use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\HTTP;
use Ocolin\Billmax\Response;
use PHPUnit\Framework\TestCase;

class HTTPTest extends TestCase
{
    private Config $config;
    private ClientInterface $mockClient;
    private TokenInterface $mockCache;


    /* SETUP
    ----------------------------------------------------------------------------- */

    protected function setUp(): void
    {
        $this->config = new Config(
            host:   'https://billmax.example.com:3100',
            auth:   'NONE',
            apiKey: 'abc123'
        );

        $this->mockClient = $this->createStub( ClientInterface::class );
        $this->mockCache  = $this->createStub( TokenInterface::class );
    }


    /* HELPER
    ----------------------------------------------------------------------------- */

    private function makeHttp(): HTTP
    {
        return new HTTP(
            config: $this->config,
            client: $this->mockClient,
            cache:  $this->mockCache
        );
    }

    private function mockJsonResponse( mixed $data, int $status = 200 ): GuzzleResponse
    {
        return new GuzzleResponse(
            status: $status,
            body: json_encode( $data )
        );
    }


    /* REQUEST TESTS
    ----------------------------------------------------------------------------- */

    public function testSuccessfulGet(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( $this->mockJsonResponse([ ['id' => 1] ]) );

        $response = $this->makeHttp()->request( path: '/accounts/1' );

        $this->assertInstanceOf( Response::class, $response );
        $this->assertSame( 200, $response->status );
    }

    public function testSuccessfulPost(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( $this->mockJsonResponse([ ['id' => 1] ]) );

        $response = $this->makeHttp()->request(
            path:   '/accounts',
            method: 'POST',
            body:   ['firstName' => 'John']
        );

        $this->assertSame( 200, $response->status );
    }

    public function testInvalidMethodThrowsApiException(): void
    {
        $this->expectException( ApiException::class );
        $this->expectExceptionMessage( 'Invalid HTTP method: INVALID' );

        $this->makeHttp()->request(
            path:   '/accounts',
            method: 'INVALID'
        );
    }

    public function test401TriggersReAuthenticateAndRetries(): void
    {
        $mockAuth = $this->createMock( AuthInterface::class );
        $mockAuth->expects( $this->once() )->method( 'reAuthenticate' );
        $mockAuth->method( 'getHeaders' )->willReturn( [] );

        $this->mockClient
            ->method( 'request' )
            ->willReturnOnConsecutiveCalls(
                $this->mockJsonResponse( ['error' => []], 401 ),
                $this->mockJsonResponse( [['id' => 1]] )
            );

        // Use reflection to inject mock auth
        $http = $this->makeHttp();
        $reflection = new \ReflectionClass( $http );
        $property = $reflection->getProperty( 'auth' );
        $property->setAccessible( true );
        $property->setValue( $http, $mockAuth );

        $response = $http->request( path: '/accounts/1' );
        $this->assertSame( 200, $response->status );
    }

    public function testSecond401ThrowsAuthException(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( $this->mockJsonResponse( ['error' => []], 401 ) );

        $this->expectException( AuthException::class );
        $this->expectExceptionMessage(
            'AUTH NONE: Re-authentication not supported. Check your API key.'
        );

        $this->makeHttp()->request( path: '/accounts/1' );
    }


    /* PATCH AUTO GET TESTS
    ----------------------------------------------------------------------------- */

    public function testPatchAutoGetFetchesGeneration(): void
    {
        $generation = '2024-01-04 12:23:21';

        $this->mockClient
            ->method( 'request' )
            ->willReturnOnConsecutiveCalls(
                $this->mockJsonResponse( [['generation' => $generation]] ),
                $this->mockJsonResponse( [['id' => 1]] )
            );

        $response = $this->makeHttp()->request(
            path:   '/accounts/1',
            method: 'PATCH',
            body:   ['firstName' => 'John']
        );

        $this->assertSame( 200, $response->status );
    }

    public function testPatchWithAutoGetFalseSkipsGeneration(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( $this->mockJsonResponse( [['id' => 1]] ) );

        $response = $this->makeHttp()->request(
            path:    '/accounts/1',
            method:  'PATCH',
            body:    ['firstName' => 'John'],
            autoGet: false
        );

        $this->assertSame( 200, $response->status );
    }


    /* BUILD PATH TESTS
    ----------------------------------------------------------------------------- */

    public function testBuildPathSubstitutesVariables(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( $this->mockJsonResponse( [['id' => 1]] ) );

        $response = $this->makeHttp()->request(
            path:  '/accounts/{id}',
            query: ['id' => 1]
        );

        $this->assertSame( 200, $response->status );
    }

    public function testBuildPathLeavesNonPathQueryParams(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( $this->mockJsonResponse( [['id' => 1]] ) );

        $response = $this->makeHttp()->request(
            path:  '/accounts/{id}',
            query: ['id' => 1, 'months' => 6]
        );

        $this->assertSame( 200, $response->status );
    }


    /* BUILD RESPONSE TESTS
    ----------------------------------------------------------------------------- */

    public function testBuildResponseThrowsOnInvalidJson(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( new GuzzleResponse(
                status: 200,
                body: 'not json'
            ));

        $this->expectException( ApiException::class );
        $this->expectExceptionMessage( 'API: Unexpected response format.' );

        $this->makeHttp()->request( path: '/accounts' );
    }

    public function testBuildResponseHandlesArrayBody(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( $this->mockJsonResponse( [['id' => 1], ['id' => 2]] ) );

        $response = $this->makeHttp()->request( path: '/accounts' );

        $this->assertIsArray( $response->body );
    }

    public function testBuildResponseHandlesObjectBody(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturn( $this->mockJsonResponse( ['cards' => [], 'echecks' => []] ) );

        $response = $this->makeHttp()->request( path: '/accounts/1/paymentmethods' );

        $this->assertIsObject( $response->body );
    }


    /* FETCH GENERATION TESTS
    ----------------------------------------------------------------------------- */

    public function testFetchGenerationThrowsWhenMissing(): void
    {
        $this->mockClient
            ->method( 'request' )
            ->willReturnOnConsecutiveCalls(
                $this->mockJsonResponse( [['id' => 1]] ),
                $this->mockJsonResponse( [['id' => 1]] )
            );

        $this->expectException( ApiException::class );
        $this->expectExceptionMessage( 'No generation found.' );

        $this->makeHttp()->request(
            path:   '/accounts/1',
            method: 'PATCH',
            body:   ['firstName' => 'John']
        );
    }
}