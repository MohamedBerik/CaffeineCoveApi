<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $companyId;
    public string $type;
    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(int $companyId, string $type, array $data)
    {
        $this->companyId = $companyId;
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     * ✅ استخدام PrivateChannel للأمان (يتطلب صلاحية)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.' . $this->companyId . '.dashboard')
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'dashboard.updated';
    }

    /**
     * Get the data to broadcast.
     * ✅ تحسين البيانات المرسلة
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * ✅ تحديد اسم الـ Queue
     */
    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
