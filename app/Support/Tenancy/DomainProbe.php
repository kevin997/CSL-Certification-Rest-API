<?php

namespace App\Support\Tenancy;

interface DomainProbe
{
    /** Whether the host resolves in DNS and answers HTTPS with a non-5xx status. */
    public function isLive(string $host): bool;
}
