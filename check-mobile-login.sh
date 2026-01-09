#!/bin/bash

# Comando rápido para verificar errores CSRF en login móvil
# Uso: ./check-mobile-login.sh

LOG_FILE="storage/logs/laravel.log"

echo "🔍 Verificando logs de login móvil..."
echo ""

# Primero verificar si hay ALGÚN log de LOGIN-MOBILE-DEBUG
DEBUG_COUNT=$(tail -1000 "$LOG_FILE" | grep -c "LOGIN-MOBILE-DEBUG")

if [ $DEBUG_COUNT -eq 0 ]; then
    echo "⚠️  NO se encontraron logs de LOGIN-MOBILE-DEBUG"
    echo ""
    echo "Posibles causas:"
    echo "  1. No has accedido a /admin/login desde móvil después de los cambios"
    echo "  2. Los cambios no se aplicaron - ejecuta: php artisan config:clear"
    echo "  3. Hay un error de sintaxis en Login.php"
    echo ""
    echo "📝 Para verificar manualmente ejecuta:"
    echo "   grep 'LOGIN-MOBILE-DEBUG' storage/logs/laravel.log"
    exit 0
fi

echo "✅ Se encontraron $DEBUG_COUNT logs de LOGIN-MOBILE-DEBUG"
echo ""

# Buscar errores CSRF
CSRF_COUNT=$(tail -1000 "$LOG_FILE" | grep "LOGIN-MOBILE-DEBUG" | grep -c "TOKEN CSRF MISMATCH\|TokenMismatchException")

if [ $CSRF_COUNT -gt 0 ]; then
    echo "❌ ERROR CSRF DETECTADO: $CSRF_COUNT ocurrencia(s) del error 'page expired'"
    echo ""
    echo "📋 Detalles del error:"
    echo ""

    tail -1000 "$LOG_FILE" | grep "LOGIN-MOBILE-DEBUG" | grep "TOKEN CSRF MISMATCH\|TokenMismatchException" | tail -5

    echo ""
    echo "💡 Para ver análisis completo ejecuta: ./parse-mobile-login.sh"
else
    echo "✅ No se detectaron errores CSRF"
    echo ""

    # Mostrar último evento detectado
    echo "📱 Último evento desde móvil:"
    tail -1000 "$LOG_FILE" | grep "LOGIN-MOBILE-DEBUG" | tail -1 | grep -o '\[LOGIN-MOBILE-DEBUG\][^{]*' || echo "  (verificar log completo)"
fi

echo ""
echo "📊 Resumen de eventos encontrados:"
tail -1000 "$LOG_FILE" | grep "LOGIN-MOBILE-DEBUG" | grep -o '\[LOGIN-MOBILE-DEBUG\][^{]*' | sort | uniq -c | head -10

echo ""
