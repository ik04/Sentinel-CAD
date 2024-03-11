<?php

use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get("healthcheck",function(){
    return response()->json(["message" => "hi from sentinel"],200);
});

Route::post("register",[UserController::class,"register"]);
Route::post("login",[UserController::class,"login"]);
Route::get("get-users",[UserController::class,"getUsers"]);
Route::get("user-data",[UserController::class,"userData"]);
Route::post('/is-user',[UserController::class,'isUser']);
Route::post('/test',[MessageController::class,'test']);

Route::middleware(["auth:sanctum"])->group(function(){
    Route::post("logout",[UserController::class,"logout"]);
    Route::post("onboard",[ProfileController::class,"onboard"]);
    Route::post("search",[UserController::class,"searchUsers"]);
    Route::post("message",[MessageController::class,"storeMessage"]); // shift after testing
    Route::post("create-room",[RoomController::class,"createRoom"]); // shift after testing
    Route::get("get-user-rooms",[RoomController::class,"getUserRooms"]); // for testing
    Route::post("/isLog", function () {  
        return response()->noContent();
    });
    Route::post('/get-messages',[MessageController::class,'fetchMessages']);
    
});

Route::middleware(["auth:sanctum","isOnboard"])->group(function(){
    Route::post("/isOnboarded", function () {  
        return response()->noContent();
    });
});

// todo: add in edit and delete functionality later if needed
// todo: add routes for frontend and work userflow