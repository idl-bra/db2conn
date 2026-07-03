<?php

namespace IdLogistics\Db2Conn\Database;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;

class Db2Connector extends Connector implements ConnectorInterface
{
    /**
     * The default PDO connection options.
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    /**
     * Establish a database connection.
     *
     * @return \PDO
     */
    public function connect(array $config)
    {
        $dsn = $this->getDsn($config);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        $connection = $this->createConnection($dsn, $config, $this->getOptions($config), $username, $password);

        $this->configureSchema($connection, $config);

        foreach ($config['after_connect'] ?? [] as $statement) {
            $connection->exec($statement);
        }

        return $connection;
    }

    /**
     * Create the ODBC DSN string from the configuration.
     *
     * Replicates the exact format from the original db2Conn.class.php
     */
    public function getDsn(array $config): string
    {
        // Opção 1: connection_string completa (preferida)
        if (! empty($config['connection_string'])) {
            $dsn = $config['connection_string'];

            return str_starts_with(strtolower($dsn), 'odbc:') ? $dsn : 'odbc:'.$dsn;
        }

        // Opção 2: DSN catalogado
        if (! empty($config['dsn'])) {
            $dsn = $config['dsn'];

            return str_starts_with(strtolower($dsn), 'odbc:') ? $dsn : 'odbc:'.$dsn;
        }

        // Opção 3: montar connection string a partir de componentes (mesmo modelo do db2Conn.class.php)
        $driver = $config['odbc_driver'] ?? 'iSeries Access ODBC Driver';
        $driver = trim($driver, '{}');

        $host = $config['host'] ?? '';
        $schema = $config['schema'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        $dsn = 'odbc:DRIVER={'.$driver.'}';
        $dsn .= ';SYSTEM='.$host;
        $dsn .= ';PROTOCOL=TCPIP';
        $dsn .= ';UID='.$username;
        $dsn .= ';PWD='.$password;

        if (! empty($schema)) {
            $dsn .= ';Default Collection='.$schema;
        }

        // Merge custom keywords
        foreach ($config['odbc_keywords'] ?? [] as $key => $value) {
            if ($value !== null && $value !== '') {
                $dsn .= ';'.$key.'='.$value;
            }
        }

        return $dsn;
    }

    /**
     * Set the default schema on the connection.
     */
    protected function configureSchema(PDO $connection, array $config): void
    {
        if (! empty($config['schema'])) {
            $schema = str_replace('"', '""', strtoupper($config['schema']));

            $connection->exec('set schema "'.$schema.'"');
        }
    }
}
