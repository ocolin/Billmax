<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Ocolin\Billmax\Auth\TokenInterface;
use Ocolin\Billmax\Billmax;
use Ocolin\Billmax\Config;
use Ocolin\Billmax\HTTP;
use Ocolin\Billmax\Response;
use PHPUnit\Framework\TestCase;

class BillmaxTest extends TestCase
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

        $this->mockClient
            ->method( 'request' )
            ->willReturn( new GuzzleResponse(
                status: 200,
                body: json_encode( [['id' => 1]] )
            ));
    }


    /* HELPER
    ----------------------------------------------------------------------------- */

    private function makeBillmax(): Billmax
    {
        return new Billmax(
            config: $this->config,
            guzzle: $this->mockClient,
            cache:  $this->mockCache
        );
    }


    /* CONSTRUCTOR TESTS
    ----------------------------------------------------------------------------- */

    public function testInstantiatesWithExplicitConfig(): void
    {
        $billmax = $this->makeBillmax();
        $this->assertInstanceOf( Billmax::class, $billmax );
    }

    public function testInstantiatesWithInjectedHttp(): void
    {
        $http = new HTTP(
            config: $this->config,
            client: $this->mockClient,
            cache:  $this->mockCache
        );

        $billmax = new Billmax( http: $http );
        $this->assertInstanceOf( Billmax::class, $billmax );
    }


    /* GET TESTS
    ----------------------------------------------------------------------------- */

    public function testGetReturnsResponse(): void
    {
        $response = $this->makeBillmax()->get( endpoint: '/accounts' );
        $this->assertInstanceOf( Response::class, $response );
    }

    public function testGetPassesQueryParameters(): void
    {
        $response = $this->makeBillmax()->get(
            endpoint: '/accounts',
            query:    ['months' => 6]
        );
        $this->assertSame( 200, $response->status );
    }

    public function testGetWithPathParameter(): void
    {
        $response = $this->makeBillmax()->get(
            endpoint: '/accounts/{id}',
            query:    ['id' => 1]
        );
        $this->assertSame( 200, $response->status );
    }


    /* POST TESTS
    ----------------------------------------------------------------------------- */

    public function testPostReturnsResponse(): void
    {
        $response = $this->makeBillmax()->post(
            endpoint: '/accounts',
            body:     ['firstName' => 'John']
        );
        $this->assertInstanceOf( Response::class, $response );
    }

    public function testPostWithQueryAndBody(): void
    {
        $response = $this->makeBillmax()->post(
            endpoint: '/accounts',
            query:    ['createUser' => true],
            body:     ['firstName' => 'John']
        );
        $this->assertSame( 200, $response->status );
    }


    /* PATCH TESTS
    ----------------------------------------------------------------------------- */

    public function testPatchWithAutoGetTrue(): void
    {
        $mockClient = $this->createStub( ClientInterface::class );
        $mockClient
            ->method( 'request' )
            ->willReturnOnConsecutiveCalls(
                new GuzzleResponse(
                    status: 200,
                    body: json_encode( [['generation' => '2024-01-04 12:23:21']] )
                ),
                new GuzzleResponse(
                    status: 200,
                    body: json_encode( [['id' => 1]] )
                )
            );

        $billmax = new Billmax(
            config: $this->config,
             cache:  $this->mockCache,
            guzzle: $mockClient,
        );

        $response = $billmax->patch(
            endpoint: '/accounts/1',
            body:     ['firstName' => 'Jane']
        );
        $this->assertSame( 200, $response->status );
    }

    public function testPatchWithAutoGetFalse(): void
    {
        $response = $this->makeBillmax()->patch(
            endpoint: '/accounts/1',
            body:     ['firstName' => 'Jane'],
            autoGet:  false
        );
        $this->assertSame( 200, $response->status );
    }


    /* DELETE TESTS
    ----------------------------------------------------------------------------- */

    public function testDeleteReturnsResponse(): void
    {
        $mockClient = $this->createStub( ClientInterface::class );
        $mockClient
            ->method( 'request' )
            ->willReturn( new GuzzleResponse( status: 204 ) );

        $billmax = new Billmax(
            config: $this->config,
            cache:  $this->mockCache,
            guzzle: $mockClient,
        );
        $response = $billmax->delete( endpoint: '/aps/1' );
        $this->assertInstanceOf( Response::class, $response );
        $this->assertSame( 204, $response->status );
    }

    public function testDeleteWithPathParameter(): void
    {
        $mockClient = $this->createStub( ClientInterface::class );
        $mockClient
            ->method( 'request' )
            ->willReturn( new GuzzleResponse( status: 204 ) );

        $billmax = new Billmax(
            config: $this->config,
            cache:  $this->mockCache,
            guzzle: $mockClient,
        );

        $response = $billmax->delete(
            endpoint: '/aps/{id}',
            query:    ['id' => 1]
        );
        $this->assertSame( 204, $response->status );
    }


    /* REQUEST TESTS
    ----------------------------------------------------------------------------- */

    public function testRequestGet(): void
    {
        $response = $this->makeBillmax()->request(
            endpoint: '/accounts',
            method:   'GET'
        );
        $this->assertSame( 200, $response->status );
    }

    public function testRequestPost(): void
    {
        $response = $this->makeBillmax()->request(
            endpoint: '/accounts',
            method:   'POST',
            body:     ['firstName' => 'John']
        );
        $this->assertSame( 200, $response->status );
    }

    public function testRequestDelete(): void
    {
        $mockClient = $this->createStub( ClientInterface::class );
        $mockClient
            ->method( 'request' )
            ->willReturn( new GuzzleResponse( status: 204 ) );

        $billmax = new Billmax(
            config: $this->config,
            cache:  $this->mockCache,
            guzzle: $mockClient,
        );

        $response = $billmax->request(
            endpoint: '/aps/1',
            method:   'DELETE'
        );
        $this->assertSame( 204, $response->status );
    }
}