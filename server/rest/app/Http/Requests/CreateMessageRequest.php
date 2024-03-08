<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "message" => "string|required|max:225",
            "room_uuid" => "uuid|required",
        ];
    }
}
