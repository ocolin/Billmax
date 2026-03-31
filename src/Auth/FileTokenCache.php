<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Auth;

use Ocolin\Billmax\Exceptions\CacheException;

class FileTokenCache implements TokenInterface
{
    private string $path;

/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    public function __construct( string $hash )
    {
        $this->path = sys_get_temp_dir() .
            '/billmax_' . $hash . '.token';

        $check = file_exists( filename: $this->path )
            ? $this->path
            : dirname( path: $this->path );

        if( !is_writable( filename: $check )) {
            throw new CacheException(
                message: "Unable to write to cache file: {$this->path}."
            );
        }
    }


/*
----------------------------------------------------------------------------- */

    /**
     * @return string|null
     * @throws CacheException
     */
    public function get(): ?string
    {
        if( !file_exists( filename: $this->path )) { return null; }

        $content = file_get_contents( filename: $this->path );
        if( $content === false ) {
            throw new CacheException(
                message: "Unable to read cache file: {$this->path}."
            );
        }

        return $content;
    }


/*
----------------------------------------------------------------------------- */

    /**
     * @param string $token
     * @return void
     * @throws CacheException
     */
    public function set( string $token ): void
    {
        $handle = fopen( filename: $this->path, mode: 'w' );
        if( $handle === false ) {
            throw new CacheException(
                message: "Unable to open cache file for writing: {$this->path}."
            );
        }
        flock( stream: $handle, operation: LOCK_EX ); // LOCK BEFORE WRITING

        try {
            $result = fwrite(stream: $handle, data: $token);
            if( $result === false ) {
                throw new CacheException(
                    message: "Unable to write to cache file: {$this->path}."
                );
            }
        }
        finally {
            flock( stream: $handle, operation: LOCK_UN ); // UNLOCK AFTER WRITING
            fclose( stream: $handle );
        }
    }


/*
----------------------------------------------------------------------------- */

    /**
     * @return void
     * @throws CacheException
     */
    public function clear(): void
    {
        if( !file_exists( $this->path )) { return; }

        $result = unlink( $this->path );
        if( $result === false ) {
            throw new CacheException(
                message: "Unable to remove cache file: {$this->path}."
            );
        }
    }
}
