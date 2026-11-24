<?php

namespace App\Http\Middleware;

/** API routes must check the staging gate before Passport resolves its keys. */
final class EnsureMcpOAuthApiEnabled extends EnsureMcpOAuthStagingEnabled
{
}
