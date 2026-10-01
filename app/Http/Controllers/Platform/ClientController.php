<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\PlatformAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(PlatformAnalyticsService $analytics): Response
    {
        return Inertia::render('Platform/Clients/Index',['clients'=>$analytics->clients()]);
    }
}
