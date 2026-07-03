<?php

namespace IdLogistics\Db2Conn\Database\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class Db2Processor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * PDO_ODBC não suporta lastInsertId(); usamos IDENTITY_VAL_LOCAL().
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int|string|null
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $connection = $query->getConnection();

        $connection->insert($sql, $values);

        // Precisa rodar na MESMA conexão do insert (write pdo).
        $result = $connection->selectOne(
            'select identity_val_local() as "insert_id" from sysibm.sysdummy1', [], false
        );

        $id = data_get($result, 'insert_id');

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of a columns query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumns($results)
    {
        return array_map(function ($result) {
            $result = (object) $result;

            $typeName = strtolower(trim((string) $result->type_name));

            $type = match (true) {
                in_array($typeName, ['char', 'character', 'varchar', 'graphic', 'vargraphic', 'binary', 'varbinary', 'clob', 'blob', 'dbclob']) => $typeName.'('.(int) $result->length.')',
                in_array($typeName, ['decimal', 'numeric', 'decfloat']) => $typeName.'('.(int) $result->precision.','.(int) $result->scale.')',
                default => $typeName,
            };

            $default = $result->default !== null ? trim((string) $result->default) : null;

            return [
                'name' => trim((string) $result->name),
                'type_name' => $typeName,
                'type' => $type,
                'collation' => null,
                'nullable' => in_array(strtoupper(trim((string) $result->nullable)), ['Y', 'YES', '1'], true),
                'default' => $default === '' ? null : $default,
                'auto_increment' => in_array(strtoupper(trim((string) $result->identity)), ['Y', 'YES', '1'], true),
                'comment' => $result->comment !== null && trim((string) $result->comment) !== '' ? trim((string) $result->comment) : null,
                'generation' => null,
            ];
        }, $results);
    }

    /**
     * Process the results of an indexes query.
     *
     * @param  array  $results
     * @return array
     */
    public function processIndexes($results)
    {
        return array_map(function ($result) {
            $result = (object) $result;

            $columns = trim((string) $result->columns);

            // SYSCAT.INDEXES (LUW) devolve COLNAMES como "+COL1+COL2" ou "-COL1".
            $columns = str_starts_with($columns, '+') || str_starts_with($columns, '-')
                ? array_values(array_filter(preg_split('/[+\-]/', $columns)))
                : explode(',', $columns);

            $rule = strtoupper(trim((string) $result->rule));

            return [
                'name' => strtolower(trim((string) $result->name)),
                'columns' => array_map(fn ($column) => trim($column), $columns),
                'type' => null,
                'unique' => in_array($rule, ['U', 'P'], true),
                'primary' => $rule === 'P',
            ];
        }, $results);
    }
}
