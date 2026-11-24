<?php

return [
    /*
     * The first release is a management UI only: these flags do not expose
     * or register an MCP HTTP endpoint. MCP OAuth and transport must pass
     * separate security acceptance before the endpoint can be activated.
     */
    'gateway_enabled' => (bool) env('MCP_GATEWAY_ENABLED', false),
    'remote_writes_enabled' => (bool) env('MCP_REMOTE_WRITES_ENABLED', false),
    'oauth_ready' => false,
    'transport_ready' => false,
];
