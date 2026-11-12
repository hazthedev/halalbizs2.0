<?php

use App\Http\Middleware\McpAccess;
use App\Mcp\Servers\HalalServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', HalalServer::class)->middleware(['throttle:30,1', McpAccess::class]);
