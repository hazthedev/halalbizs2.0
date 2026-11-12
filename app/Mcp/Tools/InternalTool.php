<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tool;

abstract class InternalTool extends Tool
{
    // Laravel MCP applies this to discovery AND direct calls by tool name.
    public function shouldRegister(): bool
    {
        return request()->attributes->get('mcp.admin') === true;
    }
}
