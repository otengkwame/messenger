<?php

namespace RTippin\Messenger\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class StatusHeartbeatRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'away' => 'required|boolean'
        ];
    }
}