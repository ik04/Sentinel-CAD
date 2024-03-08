<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMessageRequest;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function storeMessage(CreateMessageRequest $request){
        $validated = $request->validated();
        // add logic queue here
        

    }
}
