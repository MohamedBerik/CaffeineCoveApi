<?php

namespace App\Events;

use App\Models\SystemAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $alert;

    /**
     * Create a new event instance.
     */
    public function __construct(SystemAlert $alert)
    {
        \Log::info('ALERT EVENT CONSTRUCTED', [
            'id' => $alert->id
        ]);

        $this->alert = $alert;
    }

    /**
     * Get the channels the event should broadcast on.
     * ✅ قناة خاصة بالشركة: company.{companyId}
     */
    public function broadcastOn()
    {
        \Log::info('AlertCreated::broadcastOn');

        return [
            new PrivateChannel(
                'company.' . $this->alert->company_id . '.alerts'
            )
        ];
    }
    /**
     * The event's broadcast name.
     * ✅ اسم موحد يسهل التعامل معه في الـ Frontend
     */
    public function broadcastAs(): string
    {
        return 'alert.created';
    }

    /**
     * Get the data to broadcast.
     * ✅ إرسال البيانات الضرورية فقط (تقليل الـ Payload)
     */
    public function broadcastWith(): array
    {
        \Log::info('AlertCreated::broadcastWith');

        return [
            'id'       => $this->alert->id,
            'message'  => $this->alert->message,
            'priority' => $this->alert->priority,
        ];
    }

    /**
     * ✅ (اختياري) تحديد اسم الـ Queue لتنفيذ الحدث
     */
    public function broadcastQueue(): string
    {
        return 'broadcasts'; // يفصل أحداث البث عن باقي الـ Jobs
    }

    /**
     * ✅ (اختياري) إذا أردت إرسال الحدث فورًا بدون Queue
     * استخدم implements ShouldBroadcastNow بدل ShouldBroadcast
     */
}
