<?php

namespace App\Http\Controllers;

use App\Enums\Message as EnumsMessage;
use App\Exceptions\EmptyMessageException;
use App\Exceptions\FlagMessageException;
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
                $imageContents = $this->convertImageToBase64($publicPath);

                $client = new Client();
                $jsonData = [
                    "text" => "hi",
                    "id" => "string",
                    "image" => $imageContents
                ];
               $response = $client->post(env("FASTAPI_SERVER").'/check-message?return_on_any_harmful=false&return_all_results=false',[
                            'json' => $jsonData
                        ]);     
                $data = json_decode($response->getBody(), true);
                $response = ["services" => [
                    "link_detection" => true,
                    "image_detection" => true,
                    "profanity_detection" => true
                    ]];

                    if($data["services"] || $data["services"]["link_detection"] || $data["services"]["profanity"]){
                        throw new FlagMessageException(message:"Your Message Has Been Flagged",code:409);
                    }

                $message = Message::create([
                    "user_id" => $request->user()->id,
                    "room_id" => $roomId,
                    "uuid" => Uuid::uuid4(),
                    "message" => $url,
                    "type" => EnumsMessage::IMAGE->value
                ]);
                return response()->json(["message" => "Message Stored!","message" => $message]);
            }

            $client = new Client();
            $jsonData = [
                "text" => $validated["message"],
                "id" => "string",
                "image" => true
            ];
           $response = $client->post(env("FASTAPI_SERVER").'/check-message?return_on_any_harmful=false&return_all_results=false',[
                        'json' => $jsonData
                    ]);     
            $data = json_decode($response->getBody(), true);
        
            if($data["services"] || $data["services"]["link_detection"] || $data["services"]["profanity"]){
                throw new FlagMessageException(message:"Your Message Has Been Flagged",code:409);
            }
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
        catch(FlagMessageException $e){
            return response()->json(["error" => $e->getMessage()],$e->getCode());
        }
        catch(Exception $e){
        }
    }

    public function test(Request $request){
        $client = new Client();
        $jsonData = [
            "text" => "kill me",
            "id" => "string",
            "image" => true
        ];
       $response = $client->post(env("FASTAPI_SERVER").'/check-message?return_on_any_harmful=false&return_all_results=false',[
                    'json' => $jsonData
                ]);     
                $data = json_decode($response->getBody(), true);
        return response()->json(["data" => $data]);
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
