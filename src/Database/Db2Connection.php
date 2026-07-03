<?php

namespace IdLogistics\Db2Conn\Database;

use Exception;
use IdLogistics\Db2Conn\Database\Query\Grammars\Db2Grammar as QueryGrammar;
use IdLogistics\Db2Conn\Database\Query\Processors\Db2Processor;
use IdLogistics\Db2Conn\Database\Schema\Grammars\Db2Grammar as SchemaGrammar;
use Illuminate\Database\Connection;
use PDO;
use Throwable;

class Db2Connection extends Connection
{
    /**
     * Get the human-readable name of the database driver.
     */
    public function getDriverTitle(): string
    {
        return 'DB2 (ODBC)';
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \IdLogistics\Db2Conn\Database\Query\Grammars\Db2Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new QueryGrammar($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \IdLogistics\Db2Conn\Database\Schema\Grammars\Db2Grammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return new SchemaGrammar($this);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \IdLogistics\Db2Conn\Database\Query\Processors\Db2Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Db2Processor;
    }

    /**
     * Escape a string value for safe SQL embedding.
     *
     * PDO_ODBC não implementa PDO::quote(); fazemos o escape manualmente.
     *
     * @param  string  $value
     * @return string
     */
    protected function escapeString($value)
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param  string  $value
     * @return string
     */
    protected function escapeBinary($value)
    {
        return "BX'".bin2hex($value)."'";
    }

    /**
     * Get the server version for the connection.
     */
    public function getServerVersion(): string
    {
        try {
            return parent::getServerVersion();
        } catch (Throwable) {
            // Alguns drivers ODBC não expõem PDO::ATTR_SERVER_VERSION.
            return '0';
        }
    }

    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     *
     * @return bool
     */
    protected function isUniqueConstraintError(Exception $exception)
    {
        // SQLSTATE 23505 = duplicate key (DB2), SQL0803 no IBM i.
        return str_contains($exception->getMessage(), '23505')
            || str_contains($exception->getMessage(), 'SQL0803');
    }
}
