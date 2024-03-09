<?php

namespace App\Http\Controllers;

use App\Exceptions\ProfileAlreadyExistsException;
use App\Http\Requests\CreateProfileRequest;
use App\Models\Profile;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class ProfileController extends Controller
{
    public function onboard(CreateProfileRequest $request){
        try{

            $validated = $request->validated();
            if(Profile::where("user_id",$request->user()->id)->exists()){
                throw new ProfileAlreadyExistsException(message:"User Profile already exists",code:409);
            }
            $profile = Profile::create([
                "username" => $validated["username"],
                "bio" => $validated["bio"],
                "uuid" => Uuid::uuid4(),
                "user_id" => $request->user()->id
            ]);
            $request->user()->update(['is_onboard' => true]); // sets as onboarded
            return response()->json(["message"=>"Profile Created!"],200);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],$e->getCode());
        }
    }
}
