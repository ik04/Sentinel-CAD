<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProfileRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class ProfileController extends Controller
{
    public function onboard(CreateProfileRequest $request){
        $validated = $request->validated();
        $profile = Profile::create([
            "username" => $validated["username"],
            "bio" => $validated["bio"],
            "uuid" => Uuid::uuid4(),
            "user_id" => $request->user()->id
        ]);
        $request->user()->update(['is_onboard' => true]); // sets as onboarded
        return response()->json(["message"=>"Profile Created!"],200);
    }
}
