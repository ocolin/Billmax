<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit;

use LogicException;
use Ocolin\Billmax\Filter;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{

/* BASIC OPERATORS
----------------------------------------------------------------------------- */

    public function testEq(): void
    {
        $result = Filter::where( 'status' )->eq( 'Open' )->build();
        $this->assertSame( 'status:eq:Open', $result );
    }

    public function testNe(): void
    {
        $result = Filter::where( 'status' )->ne( 'Closed' )->build();
        $this->assertSame( 'status:ne:Closed', $result );
    }

    public function testLike(): void
    {
        $result = Filter::where( 'company' )->like( 'widgets' )->build();
        $this->assertSame( 'company:like:widgets', $result );
    }

    public function testNlike(): void
    {
        $result = Filter::where( 'company' )->nlike( 'widgets' )->build();
        $this->assertSame( 'company:nlike:widgets', $result );
    }

    public function testGt(): void
    {
        $result = Filter::where( 'balance' )->gt( '100' )->build();
        $this->assertSame( 'balance:gt:100', $result );
    }

    public function testLt(): void
    {
        $result = Filter::where( 'balance' )->lt( '100' )->build();
        $this->assertSame( 'balance:lt:100', $result );
    }

    public function testGte(): void
    {
        $result = Filter::where( 'balance' )->gte( '100' )->build();
        $this->assertSame( 'balance:gte:100', $result );
    }

    public function testLte(): void
    {
        $result = Filter::where( 'balance' )->lte( '100' )->build();
        $this->assertSame( 'balance:lte:100', $result );
    }


/* ARRAY OPERATORS
----------------------------------------------------------------------------- */

    public function testIn(): void
    {
        $result = Filter::where( 'state' )->in( [0, 1, 2] )->build();
        $this->assertSame( 'state:in:[0,1,2]', $result );
    }

    public function testNin(): void
    {
        $result = Filter::where( 'state' )->nin( [0, 1] )->build();
        $this->assertSame( 'state:nin:[0,1]', $result );
    }

    public function testHas(): void
    {
        $result = Filter::where( 'categories' )->has( ['Residential', 'Senior'] )->build();
        $this->assertSame( 'categories:has:[Residential,Senior]', $result );
    }

    public function testNhas(): void
    {
        $result = Filter::where( 'categories' )->nhas( ['Residential'] )->build();
        $this->assertSame( 'categories:nhas:[Residential]', $result );
    }


/* FULL TEXT OPERATORS
----------------------------------------------------------------------------- */

    public function testFt(): void
    {
        $result = Filter::where( 'fulltextindex' )->ft( 'smith' )->build();
        $this->assertSame( 'fulltextindex:ft:smith', $result );
    }

    public function testFtb(): void
    {
        $result = Filter::where( 'fulltextindex' )->ftb( 'smith' )->build();
        $this->assertSame( 'fulltextindex:ftb:smith', $result );
    }

    public function testFtq(): void
    {
        $result = Filter::where( 'fulltextindex' )->ftq( 'smith' )->build();
        $this->assertSame( 'fulltextindex:ftq:smith', $result );
    }


/* MULTIPLE CONDITIONS
----------------------------------------------------------------------------- */

    public function testMultipleConditions(): void
    {
        $result = Filter::where( 'company' )->like( 'widgets' )
            ->also( 'state' )->in( [0, 2] )
            ->build();

        $this->assertSame( 'company:like:widgets&state:in:[0,2]', $result );
    }

    public function testThreeConditions(): void
    {
        $result = Filter::where( 'company' )->like( 'widgets' )
            ->also( 'state' )->in( [0, 2] )
            ->also( 'status' )->eq( 'Open' )
            ->build();

        $this->assertSame( 'company:like:widgets&state:in:[0,2]&status:eq:Open', $result );
    }


/* ESCAPE CHARACTERS
----------------------------------------------------------------------------- */

    public function testEscapesAmpersand(): void
    {
        $result = Filter::where( 'company' )->eq( 'Widgets&Gadgets' )->build();
        $this->assertSame( 'company:eq:Widgets\&Gadgets', $result );
    }

    public function testEscapesColon(): void
    {
        $result = Filter::where( 'company' )->eq( 'Time:Warner' )->build();
        $this->assertSame( 'company:eq:Time\:Warner', $result );
    }


/* LOGIC EXCEPTIONS
----------------------------------------------------------------------------- */

    public function testThrowsWhenOperatorCalledWithoutField(): void
    {
        $this->expectException( LogicException::class );
        $this->expectExceptionMessage( 'No field set.' );

        $filter = Filter::where( 'company' )->eq( 'widgets' );
        // Manually clear pendingField by calling also then eq without where
        $filter->eq( 'something' );
    }

    public function testThrowsWhenBuildCalledWithPendingField(): void
    {
        $this->expectException( LogicException::class );
        $this->expectExceptionMessage( "Filter field 'company' has no operator." );

        Filter::where( 'company' )->build();
    }
}