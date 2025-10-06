<?php

namespace App\Repositories;

use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class MessageRepository
{
    public function forTicket(Ticket $ticket, ?string $visibility = null): Collection
    {
        $query = Message::query()
            ->where('ticket_id', $ticket->getKey())
            ->orderBy('sent_at')
            ->orderBy('id')
            ->with([
                'author:id,name',
            ]);

        if ($visibility) {
            $query->where('visibility', $visibility);
        }

        return $query->get();
    }

    public function forPortal(Ticket $ticket): Collection
    {
        return Message::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('visibility', Message::VISIBILITY_PUBLIC)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->with([
                'author:id,name',
            ])
            ->get();
    }
}
