<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentRescheduled extends Model
{
    protected $table = 'appointments_rescheduled';

    protected $fillable = [
        'appointment_id',
        'reminder_date',
        'status_mail',
        'status_notifications',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'reminder_date' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
