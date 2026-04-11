<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Populate conversations with messages.
     *
     * "Me" conversations: 15–40 messages each (realistic chat history).
     * Cross-user conversations: 5–15 messages each.
     * Timestamps spread over the last 30 days.
     */
    public function run(): void
    {
        $me = User::where('email', 'test@example.com')->firstOrFail();

        Conversation::all()->each(function (Conversation $conversation) use ($me) {
            $isMyConversation = $conversation->user_one_id === $me->id
                || $conversation->user_two_id === $me->id;

            $messageCount = $isMyConversation
                ? rand(15, 40)
                : rand(5, 15);

            $participants = [$conversation->user_one_id, $conversation->user_two_id];
            $startTime = now()->subDays(30);

            for ($i = 0; $i < $messageCount; $i++) {
                $minutesOffset = (int) (($i / $messageCount) * 30 * 24 * 60);
                $jitter = rand(0, 120);

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $participants[array_rand($participants)],
                    'body' => fake()->sentence(rand(3, 15)),
                    'created_at' => $startTime->copy()->addMinutes($minutesOffset + $jitter),
                    'updated_at' => $startTime->copy()->addMinutes($minutesOffset + $jitter),
                ]);
            }
        });
    }
}
