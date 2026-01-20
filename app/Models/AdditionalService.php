<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalService extends Model
{
    use HasFactory;

    protected $table = 'additional_services';

    protected $fillable = [
        'name',
        'code',
        'brand',
        'center_code',
        'available_days',
        'description',
        'price',
        'duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'brand' => 'array',
        'center_code' => 'array',
        'available_days' => 'array',
    ];

    /**
     * Obtener todos los servicios adicionales activos para selectores
     */
    public static function getActivosParaSelector()
    {
        return self::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Scope para filtrar solo los activos
     */
    public function scopeActivos($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para ordenar por nombre
     */
    public function scopeOrdenadoPorNombre($query)
    {
        return $query->orderBy('name');
    }

    /**
     * Scope para filtrar por código de centro específico
     */
    public function scopePorCentro($query, $centerCode)
    {
        return $query->whereJsonContains('center_code', $centerCode);
    }

    /**
     * Scope para filtrar por marca específica
     */
    public function scopePorMarca($query, $marca)
    {
        $marcaNormalizada = ucfirst(strtolower($marca));
        return $query->whereJsonContains('brand', $marcaNormalizada);
    }

    /**
     * Scope para filtrar por día de la semana
     */
    public function scopePorDia($query, $day)
    {
        // Normalizar el día (monday, tuesday, etc.)
        $dayNormalized = strtolower($day);
        return $query->whereJsonContains('available_days', $dayNormalized);
    }

    /**
     * Verificar si el servicio está disponible para un centro específico
     */
    public function estaDisponibleParaCentro($centerCode)
    {
        return in_array($centerCode, $this->center_code ?? []);
    }

    /**
     * Verificar si el servicio está disponible en un día específico
     */
    public function estaDisponibleEnDia($day)
    {
        $dayNormalized = strtolower($day);
        return in_array($dayNormalized, $this->available_days ?? []);
    }

    /**
     * Verificar si el servicio está disponible para una marca específica
     */
    public function estaDisponibleParaMarca($marca)
    {
        return in_array($marca, $this->brand ?? []);
    }

    /**
     * Obtener las marcas como string separado por comas
     */
    public function getMarcasTextoAttribute()
    {
        return is_array($this->brand) ? implode(', ', $this->brand) : '';
    }

    /**
     * Obtener el nombre de los centros asignados
     */
    public function getCentroTextoAttribute()
    {
        if (empty($this->center_code)) {
            return 'Sin asignar';
        }

        $centers = \App\Models\Local::whereIn('code', $this->center_code)->get();

        if ($centers->isEmpty()) {
            return 'Centro(s) no encontrado(s)';
        }

        return $centers->pluck('name')->implode(', ');
    }

    /**
     * Obtener los días disponibles como string legible
     */
    public function getDiasTextoAttribute()
    {
        if (empty($this->available_days)) {
            return '';
        }

        $dayTranslations = [
            'monday' => 'Lunes',
            'tuesday' => 'Martes',
            'wednesday' => 'Miércoles',
            'thursday' => 'Jueves',
            'friday' => 'Viernes',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo',
        ];

        $translatedDays = array_map(function($day) use ($dayTranslations) {
            return $dayTranslations[strtolower($day)] ?? $day;
        }, $this->available_days);

        return implode(', ', $translatedDays);
    }

    /**
     * Relación: Obtener el local asociado a este servicio
     */
    public function local()
    {
        return $this->belongsTo(\App\Models\Local::class, 'center_code', 'code');
    }
}
