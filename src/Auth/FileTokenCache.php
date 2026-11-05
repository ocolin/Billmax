<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Auth;

use Ocolin\Billmax\Exceptions\CacheException;

class FileTokenCache implements TokenInterface
{
    private string $path;

/* CONSTRUCTOR
----------------------------------------------------------------------------- */

    /**
     * @param string $hash Hash for token file name.
     * @param ?string $tokenDir Optional token directory.
     * @throws CacheException
     */
    public function __construct( string $hash, ?string $tokenDir = null )
    {
        // Using ?: instead of ?? to eliminate '' and 0 as well as null.
        $dir = rtrim( string: $tokenDir ?: sys_get_temp_dir(), characters: '/\\' );

        // Absolute path check
        if( !self::isAbsolutePath( path: $dir )) {
            throw new CacheException(
                message: "Token directory must be an absolute path: {$tokenDir}."
            );
        }

        // Directory exists check
        if( !is_dir( filename: $dir )) {
            throw new CacheException(
                message: "Directory does not exist: {$dir}."
            );
        }

        // Directory is writable check
        if( !is_writable( filename: $dir )) {
            throw new CacheException(
                message: "Directory not writable: {$dir}."
            );
        }

        $this->path = $dir . '/billmax_' . $hash . '.token';

        // file exists but is not writable check
        if(
            file_exists( filename: $this->path ) &&
            !is_writable( filename: $this->path )
        ) {
            throw new CacheException(
                message: "Cache file exists but is not writable (owned by another user?): {$this->path}."
            );
        }
    }


/* GET TOKEN
----------------------------------------------------------------------------- */

    /**
     * @return string|null Token string.
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


/* SET TOKEN
----------------------------------------------------------------------------- */

    /**
     * @param string $token Token to store.
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
            // The file is empty at this point (opened with 'w'), so the token
            // is never on disk with wider permissions than 0600.
            clearstatcache( clear_realpath_cache: true, filename: $this->path );
            $mode = fileperms( filename: $this->path );

            if( $mode === false || ( $mode & 0777 ) !== 0600 ) {
                if( !chmod( filename: $this->path, permissions: 0600 )) {
                    throw new CacheException(
                        message: "Unable to restrict cache file permissions: {$this->path}."
                    );
                }
            }

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


/* CLEAR TOKEN
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



/* IS PATH ABSOLUTE?
----------------------------------------------------------------------------- */

    /**
     * Whether a path is absolute on Linux/macOS or Windows.
     *
     * Accepts /var/app, C:\app, C:/app and \\server\share.
     */
    private static function isAbsolutePath( string $path ) : bool
    {
        return preg_match( '~^(?:/|[A-Za-z]:[\\\\/]|\\\\\\\\)~', $path ) === 1;
    }
}
