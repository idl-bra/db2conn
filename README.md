# idlogistics/db2conn

Driver de conexão **DB2 para Laravel** usando somente **PDO_ODBC** — sem extensão
`ibm_db2`, sem DLL específica de DB2. Funciona com qualquer driver ODBC instalado
na máquina (IBM i Access, IBM DB2 CLI ODBC, etc.) e integra o DB2 ao Laravel como
um driver nativo: **Eloquent, Query Builder, paginação, transações e Schema Builder**.

## Requisitos

- PHP 8.2+ com as extensões `pdo` e `pdo_odbc` habilitadas
- Laravel 11, 12 ou 13
- Um driver ODBC para DB2 instalado no sistema operacional:
  - IBM i / AS400: *IBM i Access ODBC Driver* (parte do IBM i Access Client Solutions)
  - DB2 LUW: *IBM DB2 ODBC DRIVER* (parte do Db2 CLI driver / dsdriver)

## Instalação

No `composer.json` do app:

```json
"repositories": [
    { "type": "path", "url": "packages/idlogistics/db2conn", "options": { "symlink": true } }
],
"require": {
    "idlogistics/db2conn": "@dev"
}
```

```bash
composer update idlogistics/db2conn
```

O service provider é registrado automaticamente (package discovery) e injeta a
conexão `db2` em `database.connections`. Para customizar, publique o config:

```bash
php artisan vendor:publish --tag=db2-config
```

## Configuração

Via `.env` (3 opções, em ordem de precedência):

**Opção 1: Connection string completa (recomendada)**
```env
DB2_CONNECTION_STRING="Driver={iSeries Access ODBC Driver};System=192.168.1.50;PROTOCOL=TCPIP;UID=seu_usuario;PWD=sua_senha;Default Collection=MINHALIB"
DB2_PLATFORM=ibmi
DB2_SCHEMA=MINHALIB
```

**Opção 2: DSN catalogado no ODBC**
```env
DB2_DSN=AS400
DB2_PLATFORM=ibmi
DB2_SCHEMA=MINHALIB
```

**Opção 3: Host + credenciais (montagem automática)**
```env
DB2_HOST=192.168.1.50
DB2_USERNAME=seu_usuario
DB2_PASSWORD=sua_senha
DB2_ODBC_DRIVER="iSeries Access ODBC Driver"
DB2_PLATFORM=ibmi
DB2_SCHEMA=MINHALIB
```

Principais chaves do config (`config/db2.php`):

| Chave | Descrição |
|---|---|
| `connection_string` | Connection string ODBC completa (preferida) |
| `dsn` | DSN catalogado no ODBC do Windows/Linux |
| `host` | Hostname/IP do servidor (usado se `connection_string` e `dsn` vazios) |
| `username` | Usuário (necessário para montagem automática) |
| `password` | Senha (necessária para montagem automática) |
| `schema` | Executa `SET SCHEMA` logo após conectar |
| `platform` | `ibmi` (AS/400), `luw` ou `zos` — define catálogos de sistema |
| `odbc_driver` | Nome do driver ODBC instalado (padrão: `iSeries Access ODBC Driver`) |
| `odbc_keywords` | Palavras-chave ODBC extras, ex: `['NAM' => 1, 'CCSID' => 1208]` |
| `offset_style` | `fetch` (OFFSET/FETCH, padrão) ou `row_number` para servidores antigos |
| `after_connect` | Array de SQLs executados após a conexão abrir |
| `options` | Opções PDO extras, ex: `[PDO::ATTR_CASE => PDO::CASE_LOWER]` |

## Uso

### Query Builder

```php
use Illuminate\Support\Facades\DB;

$pedidos = DB::connection('db2')
    ->table('PEDIDOS')
    ->where('STATUS', 'A')
    ->orderBy('DATA_CRIACAO', 'desc')
    ->limit(50)          // -> fetch first 50 rows only
    ->get();

// Paginação funciona normalmente (OFFSET ... FETCH FIRST)
$pagina = DB::connection('db2')->table('PEDIDOS')->orderBy('ID')->paginate(25);
```

### Eloquent

```php
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $connection = 'db2';
    protected $table = 'PEDIDOS';
    protected $primaryKey = 'ID';
    public $timestamps = false;
}

$pedidos = Pedido::where('STATUS', 'A')->with('itens')->get();

$pedido = Pedido::create(['CLIENTE' => 123]); // ID via IDENTITY_VAL_LOCAL()
```

> **Dica:** o DB2 devolve nomes de colunas em MAIÚSCULAS. Se preferir atributos
> minúsculos nos models, adicione `PDO::ATTR_CASE => PDO::CASE_LOWER` em `options`.

### Schema Builder (introspecção)

```php
Schema::connection('db2')->hasTable('PEDIDOS');
Schema::connection('db2')->getColumns('PEDIDOS');
Schema::connection('db2')->getIndexes('PEDIDOS');
Schema::connection('db2')->getTables();           // schema atual
```

### Migrations

DDL básico é suportado (`create`, `dropIfExists`, índices, FKs, add/drop column):

```php
Schema::connection('db2')->create('LOGS', function (Blueprint $table) {
    $table->increments('ID');       // integer generated always as identity
    $table->string('MENSAGEM', 500);
    $table->timestamp('CRIADO_EM')->nullable();
});
```

## Particularidades do DB2 tratadas pelo pacote

**Query Builder:**
- `limit(n)` → `FETCH FIRST n ROWS ONLY`
- `offset(n)` → `OFFSET n ROWS` (ou subquery com `ROW_NUMBER()` quando `offset_style = row_number`)
- `insertGetId()` → `IDENTITY_VAL_LOCAL()` (PDO_ODBC não tem `lastInsertId`)
- `exists()` → `select case when exists (...) ... from sysibm.sysdummy1`
- `lockForUpdate()` → `FOR UPDATE WITH RS`
- `upsert()` → `MERGE INTO ... USING (VALUES ...)`
- `truncate()` → `DELETE FROM` no IBM i (TRUNCATE exige versão 7.5+)

**Conexão:**
- Escape manual (PDO_ODBC não implementa `PDO::quote`)
- Violação de chave única detectada por SQLSTATE `23505` ou `SQL0803`
  (`Model::createOrFirst`, `firstOrCreate` etc.)
- Savepoints → `SAVEPOINT ... ON ROLLBACK RETAIN CURSORS` (transações aninhadas)

**Schema (introspecção de tabelas):**
- Catálogos diferentes por plataforma: `QSYS2` (IBM i), `SYSCAT` (LUW), `SYSIBM` (z/OS)

## Limitações conhecidas

- **JSON**: operadores `->` do query builder não são suportados (lança exceção).
- **`insertOrIgnore`**: sem equivalente no DB2 (lança exceção).
- **`change()`** em migrations: não suportado — use `DB::statement('ALTER TABLE ...')`.
- **Foreign keys via introspecção** (`Schema::getForeignKeys`): não implementado.
- **`upsert` no LUW**: parameter markers dentro de `VALUES` no `MERGE` podem exigir
  `CAST` explícito em algumas versões (SQL0418N). No IBM i funciona normalmente.
- **Índices no z/OS**: introspecção não implementada (`platform = zos`).

## Testes

Os testes de grammar/connector rodam sem um DB2 real:

```bash
php artisan test tests/Unit/Db2 tests/Feature/Db2ConnectionResolutionTest.php
```
