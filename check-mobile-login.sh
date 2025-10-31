#!/bin/bash

# Comando rápido para verificar errores CSRF en login móvil
# Uso: ./check-mobile-login.sh

LOG_FILE="storage/logs/laravel.log"

echo "🔍 Verificando errores CSRF en login móvil..."
echo ""

# Buscar errores CSRF
CSRF_COUNT=$(tail -500 "$LOG_FILE" | grep -c "TOKEN CSRF MISMATCH\|TokenMismatchException")

if [ $CSRF_COUNT -gt 0 ]; then
    echo "❌ ERROR DETECTADO: $CSRF_COUNT ocurrencia(s) del error 'page expired'"
    echo ""
    echo "📋 Últimas ocurrencias:"
    echo ""

    tail -500 "$LOG_FILE" | grep -A 10 "TOKEN CSRF MISMATCH\|TokenMismatchException" | tail -20

    echo ""
    echo "💡 Para ver análisis completo ejecuta: ./parse-mobile-login.sh"
else
    echo "✅ No se detectaron errores CSRF en las últimas 500 líneas"

    # Verificar si hay logs de móvil
    MOBILE_COUNT=$(tail -500 "$LOG_FILE" | grep -c '"is_mobile":true')
    if [ $MOBILE_COUNT -gt 0 ]; then
        echo "📱 Se detectaron $MOBILE_COUNT eventos desde dispositivos móviles"
    else
        echo "⚠️  No se detectaron intentos de login desde móvil aún"
    fi
fi

echo ""
