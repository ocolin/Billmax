<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Integration;

use Ocolin\Billmax\Billmax;
use Ocolin\Billmax\Response;
use PHPUnit\Framework\TestCase;

class BillmaxTest extends TestCase
{
    private static Billmax $billmax;

    private static ?int $id;

    private static object $ap;

    public function testAddAP() : void
    {
        $output =  self::$billmax->post(
            endpoint: '/aps',
            body: [
                'name' => 'PHUnit Test AP',
                'description' => 'AP created for testing. Delete me.',
                'pop' => 'DHCPTest',
                'maximumDownloadRate' => 1000,
                'maximumUploadRate' => 1000,
                'fccTechnologyCode' => 'Other Wireline',
                'technology' => 'Wireless MDU'
            ]
        );

        $this->assertIsObject( $output );
        $this->assertObjectHasProperty( 'body', $output );
        $this->assertIsArray( $output->body );
        $this->assertNotEmpty( $output->body );
        $this->assertIsObject( $output->body[0] );
        $this->assertObjectHasProperty( 'name', $output->body[0] );
        $this->assertSame( 'PHUnit Test AP', $output->body[0]->name );

        self::$id = $output->body[0]->id;
    }

    public function testPatchAP() : void
    {
        $output = self::$billmax->patch(
            endpoint: '/aps/{id}',
            query: [ 'id' => self::$id ],
            body: [ 'name' => 'PHUnit Update AP' ]
        );
        $this->assertIsObject( $output );
        $this->assertObjectHasProperty( 'body', $output );
        $this->assertIsArray( $output->body );
        $this->assertNotEmpty( $output->body );
        $this->assertIsObject( $output->body[0] );
        $this->assertObjectHasProperty( 'name', $output->body[0] );
        $this->assertSame( 'PHUnit Update AP', $output->body[0]->name );
    }

    public function testGetAP() : void
    {
        $output = self::$billmax->get(
            endpoint: '/aps/{id}', query: [ 'id' => self::$id ]
        );
        $this->assertIsObject( $output );
        $this->assertObjectHasProperty( 'body', $output );
        $this->assertIsArray( $output->body );
        $this->assertNotEmpty( $output->body );
        $this->assertIsObject( $output->body[0] );
        $this->assertObjectHasProperty( 'name', $output->body[0] );
        $this->assertSame( 'PHUnit Update AP', $output->body[0]->name );
    }

    public static function setUpBeforeClass(): void
    {
        self::$billmax = new Billmax();
    }

    public static function tearDownAfterClass(): void
    {
        if( self::$id !== null ) {
            self::deleteAP();
        }

        self::$id = null;
    }




    private static function deleteAP() : Response
    {
        return self::$billmax->delete(
            endpoint: '/aps/{id}',query: [ 'id' => self::$id ]
        );
    }
}