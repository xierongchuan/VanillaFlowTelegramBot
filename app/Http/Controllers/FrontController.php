<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;
use Log;

class FrontController extends Controller
{
    /**
     * Handle the telegram webhook request.
     */
    public function webhook(Nutgram $bot)
    {
        Log::info('Received request');
        $bot->run();
    }
}
