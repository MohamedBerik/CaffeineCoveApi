<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InsightGenerated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $companyId;
    public array $insight;
    public int $branchId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $companyId,
        int $branchId,
        array $insight
    ) {
        $this->companyId = $companyId;
        $this->branchId = $branchId;
        $this->insight = $insight;
    }
    /**
     * Get the channels the event should broadcast on.
     * ✅ استخدام PrivateChannel للأمان (يتطلب صلاحية)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'company.' .
                    $this->companyId .
                    '.branch.' .
                    $this->branchId .
                    '.insights'
            )
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'insight.generated';
    }

    /**
     * Get the data to broadcast.
     * ✅ إرسال البيانات الضرورية فقط مع timestamp
     */
    public function broadcastWith(): array
    {
        return [
            'insight' => $this->insight,
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
