<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'appointment_number',
        'c4c_uuid',
        'vehicle_id',
        'premise_id',
        'customer_ruc',
        'customer_name',
        'customer_last_name',
        'customer_email',
        'customer_phone',
        'appointment_date',
        'appointment_time',
        'appointment_end_time',
        'service_mode',
        'maintenance_type',
        'comments',
        'status',
        'c4c_status',
        'is_synced',
        'synced_at',
        'rescheduled',
        // ✅ NUEVOS CAMPOS PARA MAPEO ORGANIZACIONAL
        'package_id',
        'vehicle_plate',
        'c4c_offer_id',
        'offer_created_at',
        'offer_creation_failed',
        'offer_creation_error',
        'offer_creation_attempts',
        'vehicle_brand_code',
        'center_code',
        // ✅ NUEVO CAMPO PARA ESTADOS FRONTEND
        'frontend_states',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'appointment_date' => 'date',
        'appointment_time' => 'datetime:H:i:s',
        'appointment_end_time' => 'datetime',
        'is_synced' => 'boolean',
        'synced_at' => 'datetime',
        'offer_created_at' => 'datetime',
        'offer_creation_failed' => 'boolean',
        'offer_creation_attempts' => 'integer',
        'rescheduled' => 'integer',
        'frontend_states' => 'array',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::creating(function ($appointment) {
            // Generar número de cita único si no se ha proporcionado
            if (empty($appointment->appointment_number)) {
                $appointment->appointment_number = 'CITA-'.date('Ymd').'-'.strtoupper(Str::random(5));
            }
        });
    }

    /**
     * Get the vehicle that owns the appointment.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the premise that owns the appointment.
     */
    public function premise(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'premise_id');
    }

    /**
     * ✅ NUEVA RELACIÓN: Mapeo organizacional
     */
    public function organizationalMapping(): HasOne
    {
        return $this->hasOne(CenterOrganizationMapping::class, 'center_code', 'center_code')
                    ->where('brand_code', $this->vehicle_brand_code)
                    ->where('is_active', true);
    }

    /**
     * ✅ NUEVA RELACIÓN: Productos de la cita
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * ✅ NUEVA RELACIÓN: Productos activos de la cita
     */
    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)->where('status', '02');
    }

    /**
     * ✅ NUEVA RELACIÓN: Servicios de la cita (P001)
     */
    public function services(): HasMany
    {
        return $this->hasMany(Product::class)->where('position_type', 'P001');
    }

    /**
     * ✅ NUEVA RELACIÓN: Materiales de la cita (P002)
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Product::class)->where('position_type', 'P002');
    }

    /**
     * ✅ NUEVA RELACIÓN: Servicios adicionales de la cita
     */
    public function additionalServices(): HasMany
    {
        return $this->hasMany(AppointmentAdditionalService::class);
    }

    // Relaciones eliminadas: serviceCenter, serviceType
    // Estas tablas fueron removidas del sistema

    /**
     * Get the customer's full name.
     */
    public function getCustomerFullNameAttribute(): string
    {
        return "{$this->customer_name} {$this->customer_last_name}";
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by customer.
     */
    public function scopeByCustomer($query, $customerRuc)
    {
        return $query->where('customer_ruc', $customerRuc);
    }

    /**
     * Scope a query to filter by vehicle.
     */
    public function scopeByVehicle($query, $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('appointment_date', [$startDate, $endDate]);
    }

    // Scope eliminado: scopeByServiceCenter
    // La tabla service_centers fue removida del sistema

    /**
     * Scope a query to filter by pending status.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to filter by confirmed status.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to filter by in progress status.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope a query to filter by completed status.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to filter by cancelled status.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * ✅ NUEVO MÉTODO: Verificar si puede crear oferta
     */
    public function canCreateOffer(): bool
    {
        return $this->is_synced
            && $this->c4c_uuid
            && $this->package_id
            && $this->vehicle_brand_code
            && $this->center_code
            && !$this->c4c_offer_id
            && !$this->offer_creation_failed;
    }

    /**
     * ✅ NUEVO MÉTODO: Obtener mapeo organizacional
     */
    public function getOrganizationalMapping(): ?CenterOrganizationMapping
    {
        return CenterOrganizationMapping::forCenterAndBrand(
            $this->center_code,
            $this->vehicle_brand_code
        )->first();
    }

    /**
     * ✅ NUEVO MÉTODO: Agregar estado frontend con timestamp
     */
    public function addFrontendState(string $state): void
    {
        $states = $this->frontend_states ?? [];
        $states[$state] = now()->format('Y-m-d H:i:s');
        $this->frontend_states = $states;
        $this->save();
    }

    /**
     * ✅ NUEVO MÉTODO: Verificar si tiene un estado frontend
     */
    public function hasFrontendState(string $state): bool
    {
        $states = $this->frontend_states ?? [];
        return isset($states[$state]);
    }

    /**
     * ✅ NUEVO MÉTODO: Obtener timestamp de un estado frontend
     */
    public function getFrontendStateTimestamp(string $state): ?string
    {
        $states = $this->frontend_states ?? [];
        return $states[$state] ?? null;
    }

    /**
     * ✅ NUEVO MÉTODO: Verificar si es una cita no show
     * Una cita es no show si:
     * - Tiene estado 'cita_confirmada'
     * - NO tiene estado 'en_trabajo' O han pasado más de 10 horas desde 'cita_confirmada'
     */
    public function isNoShow(): bool
    {
        $states = $this->frontend_states ?? [];
        
        // Debe tener estado 'cita_confirmada'
        if (!isset($states['cita_confirmada'])) {
            return false;
        }
        
        $citaConfirmadaTime = \Carbon\Carbon::parse($states['cita_confirmada']);
        
        // Si no tiene estado 'en_trabajo', verificar si han pasado más de 10 horas
        if (!isset($states['en_trabajo'])) {
            return $citaConfirmadaTime->addHours(10)->isPast();
        }
        
        // Si tiene estado 'en_trabajo', verificar si pasaron más de 10 horas entre ambos estados
        $enTrabajoTime = \Carbon\Carbon::parse($states['en_trabajo']);
        return $citaConfirmadaTime->diffInHours($enTrabajoTime) > 10;
    }

    /**
     * ✅ NUEVO SCOPE: Filtrar citas no show
     */
    public function scopeNoShow($query)
    {
        return $query->where(function($q) {
            // Citas que tienen 'cita_confirmada' pero no 'en_trabajo' y han pasado más de 10 horas
            $q->whereRaw("JSON_EXTRACT(frontend_states, '$.cita_confirmada') IS NOT NULL")
              ->whereRaw("JSON_EXTRACT(frontend_states, '$.en_trabajo') IS NULL")
              ->whereRaw("TIMESTAMPDIFF(HOUR, STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(frontend_states, '$.cita_confirmada')), '%Y-%m-%d %H:%i:%s'), NOW()) > 10");
        })->orWhere(function($q) {
            // O citas que tienen ambos estados pero pasaron más de 10 horas entre ellos
            $q->whereRaw("JSON_EXTRACT(frontend_states, '$.cita_confirmada') IS NOT NULL")
              ->whereRaw("JSON_EXTRACT(frontend_states, '$.en_trabajo') IS NOT NULL")
              ->whereRaw("TIMESTAMPDIFF(HOUR, STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(frontend_states, '$.cita_confirmada')), '%Y-%m-%d %H:%i:%s'), STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(frontend_states, '$.en_trabajo')), '%Y-%m-%d %H:%i:%s')) > 10");
        });
    }
}
