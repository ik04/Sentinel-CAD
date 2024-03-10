<?php

namespace App\Http\Controllers;

use App\Exceptions\IncorrectPasswordException;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\SearchUserRequest;
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
        return response()->json(["message" => "Account Created!","user"=>$user],200);
    }
    public function login(LoginUserRequest $request){
        try{
            $validated = $request->validated();
            $user = User::where("email",$validated["email"])->first();
            if(!Hash::check($validated["password"],$user->password)){
                throw new IncorrectPasswordException(message:"Incorrect Password",code:401);
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
            'uuid' => $user->uuid,
            'is_onboard' => $user->is_onboard,
            'access_token' => $request -> cookie('at'),
        ],200);
    }
    public function getUsers(){
        return response()->json(["users" => User::all()],200);
    }
    public function searchUsers(SearchUserRequest $request){
        $validated = $request->validated();
        if($request->has('search')){    
            $users = User::select('name', 'uuid')
            ->where(function ($query) use ($validated, $request) {
                $query->where('name', 'LIKE', '%' . $validated['search'] . '%')
                    ->orWhere('name', 'LIKE', '%' . $validated['search'] . '%');
            })
            ->where('id', '!=', $request->user()->id)
            ->get();        
            return response()->json($users,200);
        }else{
            $users = User::select('name','uuid')->where('id', '!=', $request->user()->id)
            ->get();
            return response()->json($users,200);
        }
    }
    public function isUser(Request $request){
        $fields = $request->validate([
            'user_uuid' =>'uuid',
        ]);
        $check = User::where('uuid',$fields['user_uuid'])->first();
        if($check){
            return response()->json($check,200);
        }else{
            return response("user not found",404); 
        }
    }


}
