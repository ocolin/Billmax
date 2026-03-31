<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit\Auth;

use Ocolin\Billmax\Auth\FileTokenCache;
use Ocolin\Billmax\Exceptions\CacheException;
use PHPUnit\Framework\TestCase;

class FileTokenCacheTest extends TestCase
{
    private FileTokenCache $cache;
    private string $path;


/* SETUP
----------------------------------------------------------------------------- */

    protected function setUp(): void
    {
        $hash = md5( string: 'test_hash' );
        $this->path = sys_get_temp_dir() . '/billmax_' . $hash . '.token';
        $this->cache = new FileTokenCache( hash: 'test_hash' );

        // ensure clean state before each test
        $this->cache->clear();
        if( file_exists( $this->path )) {
            unlink( $this->path );
        }
    }


/* TEAR DOWN
----------------------------------------------------------------------------- */

    protected function tearDown(): void
    {
        // clean up after each test
        if( file_exists( $this->path )) {
            unlink( $this->path );
        }
    }


/* TESTS
----------------------------------------------------------------------------- */

    public function testGetReturnsNullWhenNoFile(): void
    {
        $test = $this->cache->get();
        $this->assertNull( $test );
    }

    public function testSetWritesToken(): void
    {
        $this->cache->set( token: 'test_token' );
        $this->assertSame( 'test_token', $this->cache->get() );
    }

    public function testClearRemovesFile(): void
    {
        $this->cache->set( token: 'test_token' );
        $this->cache->clear();
        $this->assertNull( $this->cache->get() );
    }

    public function testClearOnNonExistentFileDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->cache->clear();
    }

    public function testSetOverwritesExistingToken(): void
    {
        $this->cache->set( token: 'first_token' );
        $this->cache->set( token: 'second_token' );
        $this->assertSame( 'second_token', $this->cache->get() );
    }
}