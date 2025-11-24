<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servicios SAP (3P Soluciones)
    |--------------------------------------------------------------------------
    |
    | Configuraciones para los servicios SOAP de SAP que incluyen todos los
    | métodos Z3PF_* para gestión de clientes, vehículos, servicios, etc.
    | Todos los métodos usan el mismo WSDL y credenciales.
    |
    */

    'sap_3p' => [
        'wsdl_url' => env('SAP_3P_WSDL_URL', 'http://mitdesqafo.mitsuiautomotriz.com:8002/sap/bc/srt/wsdl/flv_10002A111AD1/bndg_url/sap/bc/srt/rfc/sap/zws_ges_cit/400/zws_gescit/zws_gescit?sap-client=400'),
        'usuario' => env('SAP_3P_USUARIO', 'USR_TRK'),
        'password' => env('SAP_3P_PASSWORD', 'Srv0103$MrvTk%'),
        'metodos' => [
            'datos_cliente' => 'Z3PF_GETDATOSCLIENTE',
            'lista_vehiculos' => 'Z3PF_GETLISTAVEHICULOS',
            'lista_servicios' => 'Z3PF_GETLISTASERVICIOS',
            'datos_asesor_proceso' => 'Z3PF_GETDATOSASESORPROCESO',
            'lista_prepago_pendiente' => 'Z3PF_GETLISTAPREPAGOPEN',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Compatibilidad con configuración anterior
    |--------------------------------------------------------------------------
    |
    | Mantenemos las configuraciones anteriores para evitar romper el código
    | existente. Estas apuntan a la misma configuración SAP 3P.
    |
    */

    'vehiculos' => [
        'wsdl_url' => env('SAP_3P_WSDL_URL', 'http://mitdesqafo.mitsuiautomotriz.com:8002/sap/bc/srt/wsdl/flv_10002A111AD1/bndg_url/sap/bc/srt/rfc/sap/zws_ges_cit/400/zws_gescit/zws_gescit?sap-client=400'),
        'usuario' => env('SAP_3P_USUARIO', 'USR_TRK'),
        'password' => env('SAP_3P_PASSWORD', 'Srv0103$MrvTk%'),
    ],

    'citas' => [
        'wsdl_url' => env('SAP_3P_WSDL_URL', 'http://mitdesqafo.mitsuiautomotriz.com:8002/sap/bc/srt/wsdl/flv_10002A111AD1/bndg_url/sap/bc/srt/rfc/sap/zws_ges_cit/400/zws_gescit/zws_gescit?sap-client=400'),
        'usuario' => env('SAP_3P_USUARIO', 'USR_TRK'),
        'password' => env('SAP_3P_PASSWORD', 'Srv0103$MrvTk%'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'register_appointment' => env('TWILIO_WHATSAPP_REGISTER_APPOINTMENT'),
        'register_rescheduled' => env('TWILIO_WHATSAPP_REGISTER_RESCHEDULED'),
        'register_annulled' => env('TWILIO_WHATSAPP_REGISTER_ANNULLED'),
        'register_reminder' => env('TWILIO_WHATSAPP_REGISTER_REMINDER')
    ],

];
