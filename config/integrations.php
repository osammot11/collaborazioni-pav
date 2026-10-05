<?php

return [
    // Exact callbacks only: never wildcard subdomains or arbitrary URLs.
    'redirect_uris' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CHATGPT_REDIRECT_URIS', 'https://chatgpt.com/connector_platform_oauth_redirect'
    ))))),
    'resource' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/mcp',
];
