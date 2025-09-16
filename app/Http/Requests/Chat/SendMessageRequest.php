<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'discussion_id' => 'required|integer|exists:course_discussions,id',
            'content' => 'required|string|max:5000',
            'type' => 'sometimes|string|in:text,file,system',
            'parent_message_id' => 'sometimes|nullable|integer|exists:discussion_messages,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'discussion_id.required' => 'Discussion ID is required',
            'discussion_id.integer' => 'Discussion ID must be an integer',
            'discussion_id.exists' => 'Discussion not found',
            'content.required' => 'Message content is required',
            'content.string' => 'Message content must be a string',
            'content.max' => 'Message content cannot exceed 5000 characters',
            'type.in' => 'Message type must be text, file, or system',
            'parent_message_id.integer' => 'Parent message ID must be an integer',
            'parent_message_id.exists' => 'Parent message not found',
        ];
    }
}