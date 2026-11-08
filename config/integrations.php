<?php

return [
    // Exact callbacks only: never wildcard subdomains or arbitrary URLs.
    'redirect_uris' => array_values(array_unique(array_filter(array_map('trim', array_merge(
        explode(',', (string) env('CHATGPT_REDIRECT_URIS', 'https://chatgpt.com/connector_platform_oauth_redirect')),
        explode(',', (string) env('CLAUDE_REDIRECT_URIS', 'https://claude.ai/api/mcp/auth_callback')),
    ))))),
    'resource' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/mcp',
    'claude_resource' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/mcp/claude',
];
