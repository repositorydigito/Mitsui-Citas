#!/bin/bash

# Script para parsear logs de login móvil y detectar problemas
# Uso: ./parse-mobile-login.sh [numero_de_lineas]

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Archivo de log
LOG_FILE="storage/logs/laravel.log"

# Número de líneas a analizar (por defecto 500)
LINES=${1:-500}

echo -e "${BOLD}${CYAN}================================================${NC}"
echo -e "${BOLD}${CYAN}   ANÁLISIS DE LOGIN MÓVIL - DEBUG${NC}"
echo -e "${BOLD}${CYAN}================================================${NC}\n"

# Verificar que existe el archivo de log
if [ ! -f "$LOG_FILE" ]; then
    echo -e "${RED}Error: No se encontró el archivo $LOG_FILE${NC}"
    exit 1
fi

# Extraer solo las últimas N líneas con LOGIN-MOBILE-DEBUG
TEMP_LOG=$(tail -n $LINES "$LOG_FILE" | grep "LOGIN-MOBILE-DEBUG")

if [ -z "$TEMP_LOG" ]; then
    echo -e "${YELLOW}⚠️  No se encontraron logs de LOGIN-MOBILE-DEBUG en las últimas $LINES líneas${NC}"
    echo -e "${YELLOW}   Intenta con un número mayor: ./parse-mobile-login.sh 1000${NC}"
    exit 0
fi

# Función para extraer JSON de forma simple
extract_json_value() {
    local json="$1"
    local key="$2"
    echo "$json" | grep -o "\"$key\":[^,}]*" | cut -d':' -f2- | tr -d '"' | xargs
}

echo -e "${BOLD}📊 RESUMEN DE EVENTOS${NC}\n"

# Contar eventos por tipo
echo -e "${CYAN}Eventos detectados:${NC}"
echo "$TEMP_LOG" | grep -o "\[LOGIN-MOBILE-DEBUG\][^{]*" | sort | uniq -c | sort -rn
echo ""

# Detectar CSRF Token Mismatch
CSRF_ERRORS=$(echo "$TEMP_LOG" | grep -i "TOKEN CSRF MISMATCH\|TokenMismatchException")
if [ ! -z "$CSRF_ERRORS" ]; then
    echo -e "${RED}${BOLD}🚨 ¡ERROR CSRF DETECTADO!${NC}\n"
    echo -e "${RED}Se encontró el error 'The page has expired'${NC}\n"

    # Extraer detalles del error CSRF
    echo -e "${BOLD}Detalles del error:${NC}"
    echo "$CSRF_ERRORS" | while IFS= read -r line; do
        timestamp=$(echo "$line" | grep -o '\[20[0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]\]' | head -1)

        # Intentar extraer información del JSON
        is_mobile=$(echo "$line" | grep -o '"is_mobile":[^,}]*' | cut -d':' -f2 | xargs)
        device_type=$(echo "$line" | grep -o '"device_type":"[^"]*' | cut -d':' -f2 | tr -d '"')
        os=$(echo "$line" | grep -o '"os":"[^"]*' | cut -d':' -f2 | tr -d '"' | head -1)

        if [ ! -z "$timestamp" ]; then
            echo -e "  ${YELLOW}Timestamp:${NC} $timestamp"
        fi
        if [ "$is_mobile" == "true" ] || [ "$is_mobile" == "1" ]; then
            echo -e "  ${YELLOW}Dispositivo:${NC} ${RED}MÓVIL${NC} ($device_type - $os)"
        else
            echo -e "  ${YELLOW}Dispositivo:${NC} Desktop"
        fi
        echo ""
    done
else
    echo -e "${GREEN}✅ No se detectaron errores CSRF${NC}\n"
fi

# Análisis de dispositivos móviles
echo -e "${BOLD}📱 ANÁLISIS DE DISPOSITIVOS${NC}\n"

MOBILE_LOGINS=$(echo "$TEMP_LOG" | grep '"is_mobile":true' | wc -l)
DESKTOP_LOGINS=$(echo "$TEMP_LOG" | grep '"is_mobile":false' | wc -l)
ANDROID_COUNT=$(echo "$TEMP_LOG" | grep -i '"device_type":"Android"' | wc -l)
IOS_COUNT=$(echo "$TEMP_LOG" | grep -i '"device_type":"iPhone"\|"device_type":"iPad"' | wc -l)

echo -e "${CYAN}Intentos de login desde móvil:${NC} $MOBILE_LOGINS"
echo -e "${CYAN}Intentos de login desde desktop:${NC} $DESKTOP_LOGINS"

if [ $ANDROID_COUNT -gt 0 ]; then
    echo -e "${CYAN}  - Android:${NC} $ANDROID_COUNT"
fi
if [ $IOS_COUNT -gt 0 ]; then
    echo -e "${CYAN}  - iOS:${NC} $IOS_COUNT"
fi
echo ""

# Últimos eventos móviles con detalles
echo -e "${BOLD}📋 ÚLTIMOS 5 EVENTOS DESDE MÓVIL${NC}\n"

echo "$TEMP_LOG" | grep '"is_mobile":true' | tail -5 | while IFS= read -r line; do
    # Extraer información
    timestamp=$(echo "$line" | grep -o '\[20[0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]\]' | head -1)
    event=$(echo "$line" | grep -o "\[LOGIN-MOBILE-DEBUG\][^{]*" | sed 's/\[LOGIN-MOBILE-DEBUG\] //')
    device_type=$(echo "$line" | grep -o '"device_type":"[^"]*' | cut -d':' -f2 | tr -d '"')
    os=$(echo "$line" | grep -o '"os":"[^"]*' | cut -d':' -f2 | tr -d '"' | head -1)
    browser=$(echo "$line" | grep -o '"browser":"[^"]*' | cut -d':' -f2 | tr -d '"')
    csrf_present=$(echo "$line" | grep -o '"csrf_token_presente":[^,}]*' | cut -d':' -f2 | xargs)
    tokens_match=$(echo "$line" | grep -o '"tokens_match":[^,}]*' | cut -d':' -f2 | xargs)

    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}$event${NC}"
    [ ! -z "$timestamp" ] && echo -e "  ⏰ $timestamp"
    [ ! -z "$device_type" ] && echo -e "  📱 Dispositivo: $device_type"
    [ ! -z "$os" ] && echo -e "  💻 OS: $os"
    [ ! -z "$browser" ] && echo -e "  🌐 Browser: $browser"

    if [ ! -z "$csrf_present" ]; then
        if [ "$csrf_present" == "true" ] || [ "$csrf_present" == "1" ]; then
            echo -e "  🔐 CSRF Token: ${GREEN}Presente${NC}"
        else
            echo -e "  🔐 CSRF Token: ${RED}AUSENTE${NC}"
        fi
    fi

    if [ ! -z "$tokens_match" ]; then
        if [ "$tokens_match" == "true" ] || [ "$tokens_match" == "1" ]; then
            echo -e "  ✅ Tokens CSRF: ${GREEN}Coinciden${NC}"
        else
            echo -e "  ❌ Tokens CSRF: ${RED}NO COINCIDEN${NC}"
        fi
    fi
    echo ""
done

# Problemas detectados
echo -e "${BOLD}🔍 PROBLEMAS DETECTADOS${NC}\n"

VALIDATION_ERRORS=$(echo "$TEMP_LOG" | grep "ValidationException" | wc -l)
TOKEN_MISMATCHES=$(echo "$TEMP_LOG" | grep -i "TokenMismatchException\|TOKEN CSRF MISMATCH" | wc -l)
GENERAL_ERRORS=$(echo "$TEMP_LOG" | grep "Excepción general capturada" | wc -l)

if [ $TOKEN_MISMATCHES -gt 0 ]; then
    echo -e "${RED}❌ Errores de CSRF Token (page expired): $TOKEN_MISMATCHES${NC}"
fi

if [ $VALIDATION_ERRORS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Errores de validación: $VALIDATION_ERRORS${NC}"
fi

if [ $GENERAL_ERRORS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Errores generales: $GENERAL_ERRORS${NC}"
fi

if [ $TOKEN_MISMATCHES -eq 0 ] && [ $VALIDATION_ERRORS -eq 0 ] && [ $GENERAL_ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ No se detectaron errores en el período analizado${NC}"
fi

echo ""

# Recomendaciones si hay errores CSRF
if [ $TOKEN_MISMATCHES -gt 0 ]; then
    echo -e "${BOLD}${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}${RED}⚠️  RECOMENDACIONES PARA SOLUCIONAR${NC}"
    echo -e "${BOLD}${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

    echo -e "${CYAN}1. Verificar configuración de cookies en .env:${NC}"
    echo "   SESSION_SECURE_COOKIE=true (si usas HTTPS)"
    echo "   SESSION_SAME_SITE=lax"
    echo ""

    echo -e "${CYAN}2. Aumentar tiempo de sesión si los usuarios tardan mucho:${NC}"
    echo "   SESSION_LIFETIME=720  # 12 horas"
    echo ""

    echo -e "${CYAN}3. Verificar que APP_URL coincida con el dominio:${NC}"
    echo "   APP_URL=https://tu-dominio.com"
    echo ""

    echo -e "${CYAN}4. Limpiar cache después de cambios:${NC}"
    echo "   php artisan config:clear"
    echo "   php artisan cache:clear"
    echo ""
fi

echo -e "${BOLD}${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${CYAN}   FIN DEL ANÁLISIS${NC}"
echo -e "${BOLD}${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

# Mostrar comando para logs en tiempo real
echo -e "${BLUE}💡 Tip: Para ver logs en tiempo real usa:${NC}"
echo -e "   ${CYAN}tail -f storage/logs/laravel.log | grep LOGIN-MOBILE-DEBUG${NC}\n"
