<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::prefix('db2-test')->group(function () {
    // Dashboard
    Route::get('/', function () {
        return view('db2conn::db2-test.dashboard');
    })->name('db2-test.dashboard');

    // Testar conexão
    Route::get('/conexao', function () {
        try {
            $version = DB::connection('db2')->getServerVersion();

            return response()->json([
                'status' => 'ok',
                'message' => 'Conectado ao DB2',
                'driver' => DB::connection('db2')->getDriverTitle(),
                'server_version' => $version,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'erro',
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ], 500);
        }
    });

    // Listar tabelas
    Route::get('/tabelas', function () {
        try {
            $schema = DB::connection('db2')->getConfig('schema');
            $tables = Schema::connection('db2')->getTables($schema);

            return response()->json([
                'status' => 'ok',
                'schema' => $schema ?? 'default',
                'count' => count($tables),
                'tables' => array_map(fn ($t) => [
                    'name' => $t['name'],
                    'schema' => $t['schema'] ?? null,
                ], $tables),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'erro',
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ], 500);
        }
    });

    // Verificar tabela
    Route::get('/tabela/{table}', function ($table) {
        try {
            $exists = Schema::connection('db2')->hasTable($table);

            if (!$exists) {
                return response()->json([
                    'status' => 'nao_encontrada',
                    'table' => $table,
                ], 404);
            }

            $columns = Schema::connection('db2')->getColumns($table);

            return response()->json([
                'status' => 'ok',
                'table' => $table,
                'columns_count' => count($columns),
                'columns' => array_map(fn ($c) => [
                    'name' => $c['name'],
                    'type' => $c['type'],
                    'nullable' => $c['nullable'],
                    'default' => $c['default'] ?? null,
                ], $columns),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'erro',
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ], 500);
        }
    });

    // Query customizada
    Route::get('/query', function () {
        $sql = request()->query('sql');

        if (!$sql) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Envie ?sql=SELECT%20...',
            ], 400);
        }

        try {
            $results = DB::connection('db2')->select($sql);

            return response()->json([
                'status' => 'ok',
                'sql' => $sql,
                'rows' => count($results),
                'data' => $results,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'erro',
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'sql' => $sql,
            ], 500);
        }
    });

    // SQL gerado (Grammar)
    Route::get('/grammar', function () {
        try {
            $conn = DB::connection('db2');

            return response()->json([
                'status' => 'ok',
                'grammar_tests' => [
                    'select' => $conn->table('SEATABLE')->toSql(),
                    'where' => $conn->table('SEATABLE')->where('ID', 1)->toSql(),
                    'limit' => $conn->table('SEATABLE')->limit(10)->toSql(),
                    'offset_limit' => $conn->table('SEATABLE')->orderBy('ID')->offset(5)->limit(10)->toSql(),
                    'paginate' => $conn->table('SEATABLE')->orderBy('ID')->limit(25)->offset(25)->toSql(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'erro',
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ], 500);
        }
    });
});
