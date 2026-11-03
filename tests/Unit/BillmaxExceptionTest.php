<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit;

use Exception;
use Ocolin\Billmax\Exceptions\ApiException;
use Ocolin\Billmax\Exceptions\AuthException;
use Ocolin\Billmax\Exceptions\BillmaxException;
use Ocolin\Billmax\Exceptions\CacheException;
use Ocolin\Billmax\Exceptions\ConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BillmaxExceptionTest extends TestCase
{

/* PROVIDER
----------------------------------------------------------------------------- */

    /**
     * @return array<string, array{class-string<BillmaxException>}>
     */
    public static function exceptionProvider(): array
    {
        return [
            'ApiException'    => [ ApiException::class ],
            'AuthException'   => [ AuthException::class ],
            'CacheException'  => [ CacheException::class ],
            'ConfigException' => [ ConfigException::class ],
        ];
    }


    /* TESTS
    ----------------------------------------------------------------------------- */

    /**
     * @param class-string<BillmaxException> $class
     */
    #[DataProvider( 'exceptionProvider' )]
    public function testExtendsBaseException( string $class ): void
    {
        $this->assertInstanceOf( BillmaxException::class, new $class( 'test' ) );
    }

    /**
     * @param class-string<BillmaxException> $class
     */
    #[DataProvider( 'exceptionProvider' )]
    public function testStillCatchableAsException( string $class ): void
    {
        $this->assertInstanceOf( Exception::class, new $class( 'test' ) );
    }

    public function testBaseExceptionIsAbstract(): void
    {
        $this->assertTrue( ( new ReflectionClass( BillmaxException::class ) )->isAbstract() );
    }

    public function testPreviousExceptionIsPreserved(): void
    {
        $original = new Exception( 'original' );
        $wrapped  = new AuthException( 'wrapped', 0, $original );

        $this->assertSame( $original, $wrapped->getPrevious() );
    }

    public function testSingleCatchCoversAllPackageExceptions(): void
    {
        $caught = 0;

        foreach( self::exceptionProvider() as [ $class ] ) {
            try {
                throw new $class( 'test' );
            } catch( BillmaxException ) {
                $caught++;
            }
        }

        $this->assertSame( 4, $caught );
    }
}