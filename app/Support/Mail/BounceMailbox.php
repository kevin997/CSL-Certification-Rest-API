<?php

namespace App\Support\Mail;

use Closure;

/**
 * Where bounce reports for Kursa's outgoing mail arrive.
 */
interface BounceMailbox
{
    /**
     * Hand every candidate bounce report to $handle as a raw RFC 822 message.
     * A report the handler returns true for is filed away so it is not read
     * again; anything else is left where it was.
     *
     * @param  Closure(string): bool  $handle
     * @return int the number of reports filed
     */
    public function process(Closure $handle): int;
}
