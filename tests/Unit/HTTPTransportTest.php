<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Ocolin\Billmax\Auth\TokenInterface;
use Ocolin\Billmax\Config;
use Ocolin\Billmax\Exceptions\ApiException;
use Ocolin\Billmax\HTTP;
use PHPUnit\Framework\TestCase;

class HTTPTransportTest extends TestCase
{
    private const string HOST = 'https://billmax.example.com:3100';


/* HELPERS
----------------------------------------------------------------------------- */

    /**
     * @param list<GuzzleResponse|\Throwable> $queue
     */
    private function makeClient( array $queue, ?MockHandler &$mock = null ): GuzzleClient
    {
        $mock = new MockHandler( $queue );

        return new GuzzleClient([ 'handler' => HandlerStack::create( $mock ) ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function noneHttp( GuzzleClient $client, array $options = [] ): HTTP
    {
        return new HTTP(
            config: new Config(
                host:    self::HOST,
                auth:    'NONE',
                apiKey:  'abc123',
                options: $options
            ),
            client: $client
        );
    }

    private function userPassHttp( GuzzleClient $client ): HTTP
    {
        $cache = $this->createStub( TokenInterface::class );
        $cache->method( 'get' )->willReturn( 'old_token' );

        return new HTTP(
            config: new Config(
                host:     self::HOST,
                auth:     'USERPASS',
                apiKey:   'abc123',
                username: 'john',
                password: 'secret'
            ),
            client: $client,
            cache:  $cache
        );
    }

    private function json( mixed $data, int $status = 200 ): GuzzleResponse
    {
        return new GuzzleResponse( status: $status, body: json_encode( $data ) );
    }


/* TRANSPORT FAILURES
----------------------------------------------------------------------------- */

    public function testConnectFailureBecomesApiExceptionWithPrevious(): void
    {
        $original = new ConnectException(
            'Connection refused', new Request( 'GET', '/accounts' )
        );
        $http = $this->noneHttp( $this->makeClient( [ $original ] ) );

        try {
            $http->request( path: '/accounts' );
            $this->fail( 'Expected ApiException was not thrown.' );
        }
        catch( ApiException $e ) {
            $this->assertSame( $original, $e->getPrevious() );
        }
    }

    public function testUploadConnectFailureBecomesApiExceptionWithPrevious(): void
    {
        $file = tempnam( sys_get_temp_dir(), 'billmax_test_' );
        file_put_contents( $file, 'hello' );

        $original = new ConnectException(
            'Connection refused', new Request( 'POST', '/files' )
        );
        $http = $this->noneHttp( $this->makeClient( [ $original ] ) );

        try {
            $http->upload(
                entity:    'account',
                entityId:  1,
                filePaths: [[ 'path' => $file, 'description' => 'test' ]]
            );
            $this->fail( 'Expected ApiException was not thrown.' );
        }
        catch( ApiException $e ) {
            $this->assertSame( $original, $e->getPrevious() );
        }
        finally {
            unlink( $file );
        }
    }


/* HTTP ERRORS COME BACK AS DATA
----------------------------------------------------------------------------- */

    public function testErrorStatusReturnsResponseInsteadOfThrowing(): void
    {
        $http = $this->noneHttp( $this->makeClient( [
            $this->json( [ 'errors' => [[ 'field' => 'id', 'error' => 'not found' ]] ], 404 )
        ] ) );

        $response = $http->request( path: '/accounts/999' );

        $this->assertSame( 404, $response->status );
    }

    public function testConsumerCannotReenableHttpErrors(): void
    {
        // A 404 is used here because a 401 would trigger re-authentication,
        // which NONE mode refuses by design.
        $http = $this->noneHttp(
            $this->makeClient( [ $this->json( [ 'errors' => [] ], 404 ) ] ),
            options: [ 'http_errors' => true ]
        );

        $response = $http->request( path: '/accounts/999' );

        $this->assertSame( 404, $response->status );
    }


/* RETRY AFTER EXPIRED SESSION
----------------------------------------------------------------------------- */

    public function testExpiredSessionOnPlainGetReauthenticatesAndRetries(): void
    {
        $client = $this->makeClient( [
            $this->json( [ 'errors' => [] ], 401 ),            // expired token
            $this->json( [ 'BXSESSIONID' => 'fresh_token' ] ), // login
            $this->json( [[ 'id' => 1 ]] ),                    // retried GET
        ], $mock );

        $response = $this->userPassHttp( $client )->request( path: '/accounts/1' );

        $this->assertSame( 200, $response->status );
        $this->assertCount( 0, $mock );
    }

    public function testExpiredSessionDuringPatchGenerationFetchRetries(): void
    {
        $client = $this->makeClient( [
            $this->json( [ 'errors' => [] ], 401 ),            // generation GET, expired
            $this->json( [ 'BXSESSIONID' => 'fresh_token' ] ), // login
            $this->json( [[ 'generation' => '2024-01-04 12:23:21' ]] ), // retried GET
            $this->json( [[ 'id' => 1 ]] ),                    // the PATCH
        ], $mock );

        $response = $this->userPassHttp( $client )->request(
            path:   '/accounts/1',
            method: 'PATCH',
            body:   [ 'firstName' => 'Jane' ]
        );

        $this->assertSame( 200, $response->status );
        $this->assertCount( 0, $mock );
    }
}