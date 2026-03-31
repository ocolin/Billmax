<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax\Auth;

use Ocolin\Billmax\Exceptions\AuthException;

interface AuthInterface
{
    /**
     * Get authentication headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;


    /**
     * Re-authenticate if session is expired.
     * @return array<string, string>
     * @throws AuthException
     */
    public function reAuthenticate() : array;

}