<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Usuario;

class SamlAuth
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $samlData;
    public $action; // 'login' or 'logout'

    /**
     * Create a new event instance.
     */
    public function __construct(Usuario $user, array $samlData = [], string $action = 'login')
    {
        $this->user = $user;
        $this->samlData = $samlData;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('saml-auth.' . $this->user->id_usuario),
        ];
    }
}
