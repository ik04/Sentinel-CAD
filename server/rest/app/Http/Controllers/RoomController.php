<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidUuidException;
use App\Exceptions\UserNotFoundException;
use App\Http\Requests\CreateRoomRequest;
use App\Models\Member;
use App\Models\Room;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class RoomController extends Controller
{
    public function createRoom(CreateRoomRequest $request){
        try{

            $validated = $request->validated();
            $recipient = User::select("id")->where("uuid",$validated["recipient_uuid"])->first();
            if(!$recipient){
                throw new UserNotFoundException(message:"User does not exist",code:400);
            }
            $recipientId = $recipient->id;
            if($recipientId == $request->user()->id){
                throw new InvalidUuidException(message:"Can't message yourself",code:400);
            }
            // check if room exists
            $existingRoom = Member::select('room_id')
            ->whereIn('user_id', [$request->user()->id, $recipientId])
            ->groupBy('room_id')
            ->havingRaw('COUNT(DISTINCT user_id) = 2')
            ->first();
            
            if ($existingRoom) {
                $room = Room::where("id",$existingRoom->room_id)->select("uuid")->first();
                return response()->json(["room_uuid" => $room->uuid, "message"=> "Room uuid fetched"], 200);
            }
            $room = Room::create([
                "uuid" => Uuid::uuid4(),
            ]);
            $senderRecord = Member::create([
                "user_id" => $request->user()->id,
                "room_id" => $room->id,
            ]);
            $recipientRecord = Member::create([
                "user_id" => $recipientId,
                "room_id" => $room->id
            ]);
            return response()->json(["message"=> "Room Created!","room_uuid" => $room->uuid],200);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],$e->getCode());
        }
        }

        public function getUserRooms(Request $request){
            // for dev 
            $userRooms = Room::join("members","members.room_id","=","members.id")->select("rooms.uuid")->where("members.user_id",$request->user()->id);
            return response()->json(["rooms" => $userRooms]);

        }
    }
    