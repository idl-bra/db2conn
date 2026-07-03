<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conexões DB2 via PDO_ODBC
    |--------------------------------------------------------------------------
    |
    | Cada conexão aqui declarada é injetada automaticamente em
    | "database.connections", ficando disponível via DB::connection('db2'),
    | Eloquent ($connection = 'db2') etc.
    |
    | Formas de conexão (em ordem de precedência):
    |
    | 1. "connection_string": connection string ODBC completa (preferida)
    |    (ex: "Driver={iSeries Access ODBC Driver};System=FGE50AGNIV;PROTOCOL=TCPIP;UID=user;PWD=pass;Default Collection=lib").
    |
    | 2. "dsn": nome de um DSN catalogado no ODBC do Windows/Linux
    |    (ex: "MEUDSN").
    |
    | 3. host + "odbc_driver" + "schema": a string é montada automaticamente
    |    como: Driver={...};SYSTEM=host;PROTOCOL=TCPIP;UID=user;PWD=pass;Default Collection=schema
    |
    | "platform" também define quais catálogos de sistema são usados pelo
    | Schema Builder: ibmi = QSYS2, luw = SYSCAT, zos = SYSIBM.
    |
    | "offset_style":
    |   - fetch      -> OFFSET n ROWS FETCH FIRST m ROWS ONLY (IBM i 7.1+,
    |                   DB2 LUW 11.1+, DB2 z/OS 12+). Padrão.
    |   - row_number -> subquery com ROW_NUMBER() OVER(), para servidores
    |                   antigos sem suporte a OFFSET.
    |
    */

    'connections' => [

        'db2' => [
            'driver' => 'db2_odbc',

            // Opções de conexão (em ordem de precedência):
            'connection_string' => env('DB2_CONNECTION_STRING'),
            'dsn' => env('DB2_DSN'),

            'host' => env('DB2_HOST'),
            'database' => '', // Obrigatório pelo Laravel, mas não usado (vide schema)
            'username' => env('DB2_USERNAME'),
            'password' => env('DB2_PASSWORD'),

            // Schema/biblioteca padrão (executa SET SCHEMA após conectar)
            'schema' => env('DB2_SCHEMA'),

            // Nome do driver ODBC instalado na máquina
            'odbc_driver' => env('DB2_ODBC_DRIVER', 'IBM i Access ODBC Driver'),

            // Palavras-chave ODBC extras (ex: 'NAM' => 1, 'CCSID' => 1208)
            'odbc_keywords' => [],

            // ibmi | luw | zos
            'platform' => env('DB2_PLATFORM', 'ibmi'),

            // fetch | row_number
            'offset_style' => env('DB2_OFFSET_STYLE', 'fetch'),

            'prefix' => '',

            // Opções extras do PDO (ex: PDO::ATTR_CASE => PDO::CASE_LOWER)
            'options' => [],

            // Statements executados logo após a conexão abrir
            'after_connect' => [],
        ],

    ],

];
