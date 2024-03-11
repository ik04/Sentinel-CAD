<?php

namespace App\Http\Controllers;

use App\Enums\Message as EnumsMessage;
use App\Exceptions\EmptyMessageException;
use App\Exceptions\RoomNotFoundException;
use App\Http\Requests\CreateMessageRequest;
use App\Http\Requests\FetchMessagesRequest;
use App\Models\Message;
use App\Models\Room;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use Ramsey\Uuid\Uuid;

class MessageController extends Controller
{
    public function convertImageToBase64($imagePath){
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);
        return $base64Image;
    }
    
    public function storeMessage(CreateMessageRequest $request){
        // edgecase both message and file sent togather
        try{
            $validated = $request->validated();
            if(!(isset($validated["message"]) || isset($validated["message_file"]))){
                throw new EmptyMessageException(message:"Nothing sent in message",code:400);
            }
            $room = Room::select("id")->where("uuid",$validated["room_uuid"])->first();
            if(!$room){
                throw new RoomNotFoundException(message:"Invalid room uuid, room not found", code:404);
            }
            $roomId = $room->id;
            $client = new Client();
            if(isset($validated["message_file"])){
                // save image
                $image = $request->file('message_file');

                $extension = $image->getClientOriginalExtension();
                $imageName = 'message_' . time() . '_' . uniqid() . '.' . $extension;
                Storage::disk('public')->put("/messages/".$imageName, file_get_contents($image));
                $url = Storage::url("messages/".$imageName);
                $publicPath = public_path($url);

                $response = $client->post('https://127.0.0.1:5000/your-mom',[
                    'form_params' => [
                        "images" => $this->convertImageToBase64($publicPath)]
                ]);

                $message = Message::create([
                    "user_id" => $request->user()->id,
                    "room_id" => $roomId,
                    "uuid" => Uuid::uuid4(),
                    "message" => $url,
                    "type" => EnumsMessage::IMAGE->value
                ]);
                return response()->json(["message" => "Message Stored!","message" => $message]);
            }

            $response = $client->post('https://127.0.0.1:5000/your-mom',[
                'form_params' => [
                    "text" => $validated["message"],
                ]
            ]);

            $message = Message::create([
                "user_id" => $request->user()->id,
                "room_id" => $roomId,
                "uuid" => Uuid::uuid4(),
                "message" => $validated["message"],
                "type" => EnumsMessage::TEXT->value
            ]);
            return response()->json(["message" => "Message Stored!","message" => $message]);
        }
        catch(EmptyMessageException $e){
            return response()->json(["error" => $e->getMessage()],$e->getCode());
        }
        catch(RoomNotFoundException $e){
            return response()->json(["error" => $e->getMessage()],$e->getCode());
        }
        catch(Exception $e){
        }
    }

    public function test(Request $request){
        // edgecase both message and file sent togather
                // save image
                $image = $request->file('message_file');

                $extension = $image->getClientOriginalExtension();
                $imageName = 'message_' . time() . '_' . uniqid() . '.' . $extension;
                Storage::disk('public')->put("/messages/".$imageName, file_get_contents($image));
                $url = Storage::url("messages/".$imageName);
                $publicPath = public_path($url);
                $base64 = $this->convertImageToBase64($publicPath);
   
                return response()->json(["message" => $base64]);
    }

    public function fetchMessages(FetchMessagesRequest $request){
        try{
            $validated = $request->validated();
            $room = Room::select("id")->where("uuid",$validated["room_uuid"])->first();
            if(!$room){
                throw new RoomNotFoundException(message:"Invalid room uuid, room not found", code:404);
            }
            $roomId = $room->id;
            $messages = Message::join("users","messages.user_id","=","users.id")->where("room_id",$roomId)->get(["name","message","messages.uuid","messages.type"]);
            return response()->json($messages,200);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],$e->getCode());
        }
    }
}
