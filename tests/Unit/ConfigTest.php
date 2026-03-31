<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Tests\Unit;

use Ocolin\Billmax\Config;
use Ocolin\Billmax\Exceptions\ConfigException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private array $backup = [];


/*
----------------------------------------------------------------------------- */

    public function testValidNoneExplicit(): void
    {
        $config = new Config(
            host:   'https://billmax.example.com:3100',
            auth:   'NONE',
            apiKey: 'abc123'
        );

        $this->assertSame( 'https://billmax.example.com:3100', $config->host );
        $this->assertSame( 'NONE', $config->auth );
        $this->assertSame( 'abc123', $config->apiKey );
    }


/*
----------------------------------------------------------------------------- */

    public function testMissingHost(): void
    {
        $this->expectException( ConfigException::class );
        $this->expectExceptionMessage( "Missing Billmax host name." );

        new Config(
              auth: 'NONE',
            apiKey: 'abc123'
        );
    }


/*
----------------------------------------------------------------------------- */

    public function testMissingAuth(): void
    {
        $this->expectException( ConfigException::class );
        $this->expectExceptionMessage( "AUTH: Missing Authentication method." );

        new Config(
            host:   'https://billmax.example.com:3100',
            apiKey: 'abc123'
        );
    }


/*
----------------------------------------------------------------------------- */

    public function testMissingApiKey(): void
    {
        $this->expectException( ConfigException::class );
        $this->expectExceptionMessage( "AUTH NONE: Missing API Key." );

        new Config(
            host: 'https://billmax.example.com:3100',
            auth: 'NONE'
        );
    }


/*
----------------------------------------------------------------------------- */

    public function testInvalidAuthMode(): void
    {
        $this->expectException( ConfigException::class );
        $this->expectExceptionMessage( "AUTH: Missing Authentication method." );

        new Config(
            host:   'https://billmax.example.com:3100',
            auth:   'INVALID',
            apiKey: 'abc123'
        );
    }


/*
----------------------------------------------------------------------------- */

    public function testUserPassMissingUsername(): void
    {
        $this->expectException( ConfigException::class );
        $this->expectExceptionMessage( "AUTH USERPASS: Missing Auth username." );

        new Config(
            host:     'https://billmax.example.com:3100',
            auth:     'USERPASS',
            apiKey:   'abc123',
            password: 'secret'
        );
    }


/*
----------------------------------------------------------------------------- */

    public function testUserPassMissingPassword(): void
    {
        $this->expectException( ConfigException::class );
        $this->expectExceptionMessage( "AUTH USERPASS: Missing Auth password." );

        new Config(
            host:     'https://billmax.example.com:3100',
            auth:     'USERPASS',
            apiKey:   'abc123',
            username: 'john'
        );
    }


/*
----------------------------------------------------------------------------- */

    public function testValidUserPass(): void
    {
        $config = new Config(
            host:     'https://billmax.example.com:3100',
            auth:     'USERPASS',
            apiKey:   'abc123',
            username: 'john',
            password: 'secret'
        );

        $this->assertSame( 'USERPASS', $config->auth );
        $this->assertSame( 'john', $config->username );
    }



/*
----------------------------------------------------------------------------- */

    public function testOAuth2MissingClientId(): void
    {
        $this->expectException( ConfigException::class );
        $this->expectExceptionMessage( "AUTH OAUTH2: Missing Auth client ID." );

        new Config(
            host:         'https://billmax.example.com:3100',
            auth:         'OAUTH2',
            clientSecret: 'secret123'
        );
    }


/*
----------------------------------------------------------------------------- */

    public function testOAuth2MissingClientSecret(): void
    {
        $this->expectException( ConfigException::class );
        $this->expectExceptionMessage( "AUTH OAUTH2: Missing Auth client secret." );

        new Config(
            host:     'https://billmax.example.com:3100',
            auth:     'OAUTH2',
            clientId: 'client123'
        );
    }


/*
----------------------------------------------------------------------------- */

    public function testValidOAuth2(): void
    {
        $config = new Config(
            host:         'https://billmax.example.com:3100',
            auth:         'OAUTH2',
            clientId:     'client123',
            clientSecret: 'secret123'
        );

        $this->assertSame( 'OAUTH2', $config->auth );
        $this->assertSame( 'client123', $config->clientId );
        $this->assertSame( 'secret123', $config->clientSecret );
    }


/*
----------------------------------------------------------------------------- */

    public function testValidNoneFromEnv(): void
    {
        $_ENV['BILLMAX_API_HOST'] = 'https://billmax.example.com:3100';
        $_ENV['BILLMAX_API_AUTH'] = 'NONE';
        $_ENV['BILLMAX_API_KEY']  = 'abc123';

        $config = new Config();

        $this->assertSame( 'https://billmax.example.com:3100', $config->host );
        $this->assertSame( 'NONE', $config->auth );
        $this->assertSame( 'abc123', $config->apiKey );
    }


/*
----------------------------------------------------------------------------- */

    public function testValidUserPassFromEnv(): void
    {
        $_ENV['BILLMAX_API_HOST']     = 'https://billmax.example.com:3100';
        $_ENV['BILLMAX_API_AUTH']     = 'USERPASS';
        $_ENV['BILLMAX_API_KEY']      = 'abc123';
        $_ENV['BILLMAX_API_USERNAME'] = 'john';
        $_ENV['BILLMAX_API_PASSWORD'] = 'secret';

        $config = new Config();

        $this->assertSame( 'USERPASS', $config->auth );
        $this->assertSame( 'john', $config->username );
    }



/*
----------------------------------------------------------------------------- */

    public function testValidOAuth2FromEnv(): void
    {
        $_ENV['BILLMAX_API_HOST']     = 'https://billmax.example.com:3100';
        $_ENV['BILLMAX_API_AUTH']     = 'OAUTH2';
        $_ENV['BILLMAX_API_KEY']      = 'abc123';
        $_ENV['BILLMAX_API_OAUTH2_CLIENT_ID']    = 'myclientid';
        $_ENV['BILLMAX_API_OAUTH2_CLIENT_SECRET'] = 'secret';

        $config = new Config();

        $this->assertSame( 'OAUTH2', $config->auth );
        $this->assertSame( 'myclientid', $config->clientId );
        $this->assertSame( 'secret', $config->clientSecret );
    }




    /* SET ENVIRONMENT VARIABLES
    ----------------------------------------------------------------------------- */

    private function setEnv( array $values ): void
    {
        foreach( $values as $key => $value ) {
            $_ENV[$key] = $value;
        }
    }



/* SETUP TEST
----------------------------------------------------------------------------- */

    protected function setUp(): void
    {
        // backup current env values
        $this->backup = [
            'BILLMAX_API_HOST'     => $_ENV['BILLMAX_API_HOST']     ?? null,
            'BILLMAX_API_AUTH'     => $_ENV['BILLMAX_API_AUTH']     ?? null,
            'BILLMAX_API_KEY'      => $_ENV['BILLMAX_API_KEY']      ?? null,
            'BILLMAX_API_USERNAME' => $_ENV['BILLMAX_API_USERNAME'] ?? null,
            'BILLMAX_API_PASSWORD' => $_ENV['BILLMAX_API_PASSWORD'] ?? null,
            'BILLMAX_API_OAUTH2_CLIENT_ID'
                => $_ENV['BILLMAX_API_OAUTH2_CLIENT_ID'] ?? null,
            'BILLMAX_API_OAUTH2_CLIENT_SECRET'
                => $_ENV['BILLMAX_API_EMAIL'] ?? null,
        ];

        foreach( array_keys( $this->backup ) as $key ) {
            unset( $_ENV[$key] );
        }
    }



/* TEAR DOWN TEST
----------------------------------------------------------------------------- */

    protected function tearDown(): void
    {
        // restore env values after each test
        foreach( $this->backup as $key => $value ) {
            if( $value === null ) { unset( $_ENV[$key] ); }
            else {  $_ENV[$key] = $value; }
        }
    }
}