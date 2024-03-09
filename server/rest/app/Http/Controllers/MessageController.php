<?php

namespace App\Http\Controllers;

use App\Enums\Message as EnumsMessage;
use App\Exceptions\EmptyMessageException;
use App\Exceptions\RoomNotFoundException;
use App\Http\Requests\CreateMessageRequest;
use App\Models\Message;
use App\Models\Room;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use Ramsey\Uuid\Uuid;

class MessageController extends Controller
{
    public function storeMessage(CreateMessageRequest $request){
        // edgecase both message and file sent togather
        // try{
            $validated = $request->validated();
            if(!(isset($validated["message"]) || isset($validated["message_file"]))){
                throw new EmptyMessageException(message:"Nothing sent in message",code:400);
            }
            $room = Room::select("id")->where("uuid",$validated["room_uuid"])->first();
            if(!$room){
                throw new RoomNotFoundException(message:"Invalid room uuid, room not found", code:404);
            }
            $roomId = $room->id;
            // add queue for validated data here
            if(isset($validated["message_file"])){
                // save image
                $image = $request->file('message_file');
                $extension = $image->getClientOriginalExtension();
                $imageName = 'message_' . time() . '_' . uniqid() . '.' . $extension;
                Storage::disk('public')->put("/messages/".$imageName, file_get_contents($image));
                $url = Storage::url("messages/".$imageName);
                $message = Message::create([
                    "user_id" => $request->user()->id,
                    "room_id" => $roomId,
                    "uuid" => Uuid::uuid4(),
                    "message" => $url,
                    "type" => EnumsMessage::IMAGE->value
                ]);
                return response()->json(["message" => "Message Stored!","message" => $message]);
            }

            $message = Message::create([
                "user_id" => $request->user()->id,
                "room_id" => $roomId,
                "uuid" => Uuid::uuid4(),
                "message" => $validated["message"],
                "type" => EnumsMessage::TEXT->value
            ]);
            return response()->json(["message" => "Message Stored!","message" => $message]);
        // }catch(Exception $e){
        //     return response()->json(["error" => $e->getMessage()],$e->getCode());
        // }
            



    }
    
}
