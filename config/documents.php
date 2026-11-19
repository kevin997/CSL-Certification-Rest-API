<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document sources
    |--------------------------------------------------------------------------
    |
    | Documentation attachments are fetched server-side and streamed to the
    | learner, so the browser never receives the storage URL. Only these hosts
    | may be fetched, which keeps the endpoint from becoming an SSRF proxy.
    |
    */

    'allowed_source_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DOCUMENT_SOURCE_HOSTS', 'res.cloudinary.com,cloudinary.com'))
    ))),

];
