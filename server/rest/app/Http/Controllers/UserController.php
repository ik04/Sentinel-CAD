<?php

namespace App\Http\Controllers;

use App\Exceptions\IncorrectPasswordException;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class UserController extends Controller
{
    public function register(RegisterUserRequest $request){
        $validated = $request->validated();
        $user = User::create([
            "email" => $validated["email"],
            "name" => $validated["name"],
            "password" => Hash::make($validated["password"]),
            "uuid" => Uuid::uuid4()
        ]);
        $userToken = $user->createToken("myusertoken")->plainTextToken;
        return response()->json(["message" => "Account Created!","user"=>$user,"user_token"=>$userToken],200)->withCookie(cookie()->forever('at',$userToken));
    }
    public function login(LoginUserRequest $request){
        try{
            $validated = $request->validated();
            $user = User::where("email",$validated["email"])->first();
            if(!Hash::check($validated["password"],$user->password)){
                throw new IncorrectPasswordException(message:"Incorrect Password",code:400);
            }
            $userToken = $user->createToken("myusertoken")->plainTextToken;
            return response()->json(["user"=>$user,"user_token"=>$userToken],200)->withCookie(cookie()->forever('at',$userToken));
        }
        catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],$e->getCode());
        }
    }
    public function logout(Request $request){
        $request->user()->tokens()->delete();
        return response([
            'message' => 'logged out'
        ],200);
    }
    
    public function userData(Request $request){
        if(!$request->hasCookie("at")){
            return response()->json([
                'error' => "Unauthenticated"
            ],401);
        }
        if($token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->cookie("at"))){
            $user = $token->tokenable;
        }
        else{
            return response()->json([
                'error' => "unauthenticated"
            ],401);
        }
        if(is_null($user)){
            return response()->json([
                'error' => "Unauthenticated"
            ],401);
        }
        return response() -> json([
            'email' => $user->email,
            'name' => $user->name,
            'uuid' => $user->user_uuid,
            'role' => $user->role,
            'access_token' => $request -> cookie('at'),
        ],200);
    }
    public function getUsers(){
        return response()->json(["users" => User::all()],200);
    }


}
