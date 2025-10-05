<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        $messageId = $this->attributes['attachable_id'] ?? Message::factory()->create()->id;
        $message = Message::query()->findOrFail($messageId);

        return [
            'tenant_id' => $message->tenant_id,
            'attachable_type' => $this->attributes['attachable_type'] ?? Message::class,
            'attachable_id' => $messageId,
            'disk' => 'local',
            'path' => 'attachments/'.$this->faker->uuid.'.txt',
            'size' => $this->faker->numberBetween(100, 2048),
            'mime_type' => 'text/plain',
        ];
    }
}
