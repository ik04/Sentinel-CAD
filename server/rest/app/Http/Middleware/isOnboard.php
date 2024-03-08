<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class isOnboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if(!$request->user()->is_onboard){
            throw new AuthenticationException("Please onboard your user profile first");
        }
        return $next($request);
    }
}
