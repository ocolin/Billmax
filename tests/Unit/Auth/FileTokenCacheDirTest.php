<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit\Auth;

use Ocolin\Billmax\Auth\FileTokenCache;
use Ocolin\Billmax\Exceptions\CacheException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FileTokenCacheDirTest extends TestCase
{
    private string $dir;


    /* SETUP / TEAR DOWN
    ----------------------------------------------------------------------------- */

    protected function setUp(): void
    {
        $this->dir = rtrim( sys_get_temp_dir(), '/\\' )
            . '/billmax_test_' . uniqid( '', true );
        mkdir( $this->dir, 0700 );
    }

    protected function tearDown(): void
    {
        foreach( glob( $this->dir . '/*' ) ?: [] as $file ) {
            unlink( $file );
        }
        if( is_dir( $this->dir ) ) { rmdir( $this->dir ); }
    }

    private function pathFor( string $hash ): string
    {
        return $this->dir . '/billmax_' . $hash . '.token';
    }

    private function skipIfPermissionsUnreliable(): void
    {
        if( PHP_OS_FAMILY === 'Windows' ) {
            $this->markTestSkipped( 'File modes are not meaningful on Windows.' );
        }
    }


    /* CUSTOM DIRECTORY
    ----------------------------------------------------------------------------- */

    public function testCustomDirectoryReceivesFile(): void
    {
        $cache = new FileTokenCache( hash: 'abc', tokenDir: $this->dir );
        $cache->set( token: 'token1' );

        $this->assertFileExists( $this->pathFor( 'abc' ) );
        $this->assertSame( 'token1', $cache->get() );
    }

    public function testTrailingSlashOnDirectoryIsIgnored(): void
    {
        $cache = new FileTokenCache( hash: 'abc', tokenDir: $this->dir . '/' );
        $cache->set( token: 'token1' );

        $this->assertFileExists( $this->pathFor( 'abc' ) );
    }

    public function testEmptyDirectoryFallsBackToSystemTemp(): void
    {
        $hash = 'fallback_' . uniqid( '', true );
        $path = rtrim( sys_get_temp_dir(), '/\\' ) . '/billmax_' . $hash . '.token';

        try {
            $cache = new FileTokenCache( hash: $hash, tokenDir: '' );
            $cache->set( token: 'token1' );

            $this->assertFileExists( $path );
        }
        finally {
            if( file_exists( $path ) ) { unlink( $path ); }
        }
    }


    /* DIRECTORY ERRORS
    ----------------------------------------------------------------------------- */

    public function testMissingDirectoryThrowsAndNamesPath(): void
    {
        $missing = $this->dir . '/does_not_exist';

        $this->expectException( CacheException::class );
        $this->expectExceptionMessage( $missing );

        new FileTokenCache( hash: 'abc', tokenDir: $missing );
    }

    public function testRelativeDirectoryThrows(): void
    {
        $this->expectException( CacheException::class );
        $this->expectExceptionMessage( 'absolute path' );

        new FileTokenCache( hash: 'abc', tokenDir: 'storage/tokens' );
    }

    public function testExistingUnwritableFileThrows(): void
    {
        $this->skipIfPermissionsUnreliable();
        if( function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 ) {
            $this->markTestSkipped( 'Root ignores file permissions.' );
        }

        $path = $this->pathFor( 'abc' );
        touch( $path );
        chmod( $path, 0444 );

        $this->expectException( CacheException::class );

        new FileTokenCache( hash: 'abc', tokenDir: $this->dir );
    }


    /* FILE PERMISSIONS
    ----------------------------------------------------------------------------- */

    public function testNewFileIsOwnerOnly(): void
    {
        $this->skipIfPermissionsUnreliable();

        $cache = new FileTokenCache( hash: 'abc', tokenDir: $this->dir );
        $cache->set( token: 'token1' );

        clearstatcache();
        $this->assertSame( 0600, fileperms( $this->pathFor( 'abc' ) ) & 0777 );
    }

    public function testOldWorldReadableFileIsTightenedOnSet(): void
    {
        $this->skipIfPermissionsUnreliable();

        // Simulates a token file left behind by an older version.
        $path = $this->pathFor( 'abc' );
        touch( $path );
        chmod( $path, 0644 );

        $cache = new FileTokenCache( hash: 'abc', tokenDir: $this->dir );
        $cache->set( token: 'token1' );

        clearstatcache();
        $this->assertSame( 0600, fileperms( $path ) & 0777 );
    }

    public function testModeStaysOwnerOnlyOnSecondSet(): void
    {
        $this->skipIfPermissionsUnreliable();

        $cache = new FileTokenCache( hash: 'abc', tokenDir: $this->dir );
        $cache->set( token: 'first' );
        $cache->set( token: 'second' );

        clearstatcache();
        $this->assertSame( 0600, fileperms( $this->pathFor( 'abc' ) ) & 0777 );
        $this->assertSame( 'second', $cache->get() );
    }


    /* ABSOLUTE PATH CHECK
    ----------------------------------------------------------------------------- */

    /**
     * @return array<string, array{string, bool}>
     */
    public static function pathProvider(): array
    {
        return [
            'unix absolute'        => [ '/var/app',         true  ],
            'windows backslash'    => [ 'C:\\app',          true  ],
            'windows forward'      => [ 'C:/app',           true  ],
            'windows network'      => [ '\\\\srv\\share',   true  ],
            'relative'             => [ 'storage/tokens',   false ],
            'dot relative'         => [ './tokens',         false ],
            'drive relative'       => [ 'C:tokens',         false ],
            'rooted without drive' => [ '\\foo',            false ],
        ];
    }

    #[DataProvider( 'pathProvider' )]
    public function testIsAbsolutePath( string $path, bool $expected ): void
    {
        $method = new ReflectionMethod( FileTokenCache::class, 'isAbsolutePath' );

        $this->assertSame( $expected, $method->invoke( null, $path ) );
    }
}