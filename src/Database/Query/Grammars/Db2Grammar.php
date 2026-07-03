<?php

namespace IdLogistics\Db2Conn\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use RuntimeException;

class Db2Grammar extends Grammar
{
    /**
     * The components that make up a select clause.
     *
     * DB2 exige OFFSET antes de FETCH FIRST.
     *
     * @var string[]
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'indexHint',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'offset',
        'limit',
        'lock',
    ];

    /**
     * Compile a select query into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if ((int) $query->offset > 0 && $this->usesLegacyOffset()) {
            return $this->compileLegacyOffset($query);
        }

        return parent::compileSelect($query);
    }

    /**
     * Determine if the connection targets an old DB2 without OFFSET support.
     */
    protected function usesLegacyOffset(): bool
    {
        return ($this->connection->getConfig('offset_style') ?? 'fetch') === 'row_number';
    }

    /**
     * Compile offset/limit as a ROW_NUMBER() wrapped query (pre OFFSET-support servers).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    protected function compileLegacyOffset(Builder $query)
    {
        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $components = $this->compileComponents($query);

        $orders = $components['orders'] ?? '';

        unset($components['orders'], $components['offset'], $components['limit'], $components['lock']);

        $components['columns'] .= ', row_number() over ('.$orders.') as "laravel_row"';

        $sql = $this->concatenate($components);

        $start = (int) $query->offset + 1;

        $constraint = $query->limit
            ? 'between '.$start.' and '.((int) $query->offset + (int) $query->limit)
            : '>= '.$start;

        return 'select * from ('.$sql.') as "laravel_offset_table" '
            .'where "laravel_row" '.$constraint.' order by "laravel_row"';
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'fetch first '.(int) $limit.' rows only';
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        $offset = (int) $offset;

        return $offset ? 'offset '.$offset.' rows' : '';
    }

    /**
     * Compile an exists statement into SQL.
     *
     * DB2 não aceita "select exists(...)" fora de um predicado.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileExists(Builder $query)
    {
        return 'select case when exists ('.$this->compileSelect($query).') '
            .'then 1 else 0 end as "exists" from sysibm.sysdummy1';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  bool|string  $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        if (is_string($value)) {
            return $value;
        }

        return $value ? 'for update with rs' : 'for read only';
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string|int  $seed
     * @return string
     */
    public function compileRandom($seed)
    {
        return 'RAND()';
    }

    /**
     * Compile the SQL statement to define a savepoint.
     *
     * @param  string  $name
     * @return string
     */
    public function compileSavepoint($name)
    {
        return 'SAVEPOINT '.$name.' ON ROLLBACK RETAIN CURSORS';
    }

    /**
     * Compile an insert ignore statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        throw new RuntimeException('DB2 does not support inserting while ignoring errors.');
    }

    /**
     * Compile an insert ignore statement using a subquery into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $columns
     * @param  string  $sql
     * @return string
     */
    public function compileInsertOrIgnoreUsing(Builder $query, array $columns, string $sql)
    {
        throw new RuntimeException('DB2 does not support inserting while ignoring errors.');
    }

    /**
     * Compile an "upsert" statement into SQL (MERGE).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @param  array  $uniqueBy
     * @param  array  $update
     * @return string
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        $columnNames = array_keys(reset($values));

        $columns = $this->columnize($columnNames);

        $sql = 'merge into '.$this->wrapTable($query->from).' as "laravel_target" using (values ';

        $sql .= collect($values)
            ->map(fn ($record) => '('.$this->parameterize($record).')')
            ->implode(', ');

        $sql .= ') as "laravel_source" ('.$columns.') on ';

        $sql .= collect($uniqueBy)
            ->map(fn ($column) => '"laravel_target".'.$this->wrap($column).' = "laravel_source".'.$this->wrap($column))
            ->implode(' and ');

        if ($update) {
            $updateSql = collect($update)
                ->map(function ($value, $key) {
                    return is_numeric($key)
                        ? $this->wrap($value).' = "laravel_source".'.$this->wrap($value)
                        : $this->wrap($key).' = '.$this->parameter($value);
                })
                ->implode(', ');

            $sql .= ' when matched then update set '.$updateSql;
        }

        $sql .= ' when not matched then insert ('.$columns.') values (';

        $sql .= collect($columnNames)
            ->map(fn ($column) => '"laravel_source".'.$this->wrap($column))
            ->implode(', ');

        return $sql.')';
    }

    /**
     * Compile a truncate table statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        // IBM i só suporta TRUNCATE a partir do 7.5; DELETE funciona em todos.
        $sql = ($this->connection->getConfig('platform') ?? 'ibmi') === 'ibmi'
            ? 'delete from '.$this->wrapTable($query->from)
            : 'truncate table '.$this->wrapTable($query->from).' immediate';

        return [$sql => []];
    }
}
