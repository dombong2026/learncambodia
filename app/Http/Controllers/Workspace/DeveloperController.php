<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    public function apis(): View
    {
        return view('workspace.developer.apis', ['role' => 'developer']);
    }

    public function usage(): View
    {
        return view('workspace.developer.usage', ['role' => 'developer']);
    }
}
