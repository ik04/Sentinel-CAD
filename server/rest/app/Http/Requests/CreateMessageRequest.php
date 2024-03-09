<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "message" => "nullable|string|max:225",
            "message_file" => "nullable|image|mimes:png,jpeg,jpg",
            "room_uuid" => "uuid|required",
        ];
    }
}
