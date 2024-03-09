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

Route::middleware(["auth:sanctum"])->group(function(){
    Route::post("logout",[UserController::class,"logout"]);
    Route::post("onboard",[ProfileController::class,"onboard"]);
});

Route::middleware(["auth:sanctum","isOnboard"])->group(function(){
    Route::post("create-room",[RoomController::class,"createRoom"]);
    Route::post("message",[MessageController::class,"storeMessage"]);
});


// todo: add in edit and delete functionality later if needed