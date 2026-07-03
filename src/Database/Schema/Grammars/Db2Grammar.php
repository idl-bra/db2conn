<?php

namespace IdLogistics\Db2Conn\Database\Schema\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use RuntimeException;

class Db2Grammar extends Grammar
{
    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected $modifiers = ['Nullable', 'Default', 'Increment'];

    /**
     * The columns available as serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Get the configured DB2 platform (ibmi, luw or zos).
     */
    protected function platform(): string
    {
        return $this->connection->getConfig('platform') ?? 'ibmi';
    }

    /**
     * Compile the schema filter clause for catalog queries.
     */
    protected function compileSchemaFilter(string $column, $schema): string
    {
        if (empty($schema)) {
            return $column.' = current schema';
        }

        $schemas = collect((array) $schema)
            ->map(fn ($value) => $this->quoteString(strtoupper($value)))
            ->implode(', ');

        return $column.' in ('.$schemas.')';
    }

    /**
     * Compile the query to determine if the given table exists.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileTableExists($schema, $table)
    {
        [$catalog, $schemaColumn, $tableColumn, $typeFilter] = match ($this->platform()) {
            'luw' => ['syscat.tables', 'tabschema', 'tabname', "type in ('T', 'S', 'U')"],
            'zos' => ['sysibm.systables', 'creator', 'name', "type in ('T', 'P')"],
            default => ['qsys2.systables', 'table_schema', 'table_name', "table_type in ('T', 'P')"],
        };

        return sprintf(
            'select count(*) from %s where upper(%s) = %s and %s and %s',
            $catalog,
            $tableColumn,
            $this->quoteString(strtoupper($table)),
            $this->compileSchemaFilter($schemaColumn, $schema),
            $typeFilter
        );
    }

    /**
     * Compile the query to determine the tables.
     *
     * @param  string|string[]|null  $schema
     * @return string
     */
    public function compileTables($schema)
    {
        [$catalog, $schemaColumn, $tableColumn, $typeFilter] = match ($this->platform()) {
            'luw' => ['syscat.tables', 'tabschema', 'tabname', "type in ('T', 'S', 'U')"],
            'zos' => ['sysibm.systables', 'creator', 'name', "type in ('T', 'P')"],
            default => ['qsys2.systables', 'table_schema', 'table_name', "table_type in ('T', 'P')"],
        };

        return sprintf(
            'select trim(%s) as "name", trim(%s) as "schema" from %s where %s and %s order by %s',
            $tableColumn,
            $schemaColumn,
            $catalog,
            $typeFilter,
            $this->compileSchemaFilter($schemaColumn, $schema),
            $tableColumn
        );
    }

    /**
     * Compile the query to determine the columns.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileColumns($schema, $table)
    {
        return match ($this->platform()) {
            'luw' => sprintf(
                'select colname as "name", typename as "type_name", length as "length", '
                .'length as "precision", scale as "scale", nulls as "nullable", '
                .'"DEFAULT" as "default", identity as "identity", remarks as "comment" '
                .'from syscat.columns where upper(tabname) = %s and %s order by colno',
                $this->quoteString(strtoupper($table)),
                $this->compileSchemaFilter('tabschema', $schema)
            ),
            'zos' => sprintf(
                'select name as "name", coltype as "type_name", length as "length", '
                .'length as "precision", scale as "scale", nulls as "nullable", '
                .'case when "DEFAULT" in (\'Y\', \'S\', \'U\') then defaultvalue else null end as "default", '
                .'case when "DEFAULT" in (\'I\', \'J\') then \'Y\' else \'N\' end as "identity", '
                .'remarks as "comment" '
                .'from sysibm.syscolumns where upper(tbname) = %s and %s order by colno',
                $this->quoteString(strtoupper($table)),
                $this->compileSchemaFilter('tbcreator', $schema)
            ),
            default => sprintf(
                'select column_name as "name", data_type as "type_name", length as "length", '
                .'numeric_precision as "precision", numeric_scale as "scale", is_nullable as "nullable", '
                .'column_default as "default", is_identity as "identity", column_text as "comment" '
                .'from qsys2.syscolumns where upper(table_name) = %s and %s order by ordinal_position',
                $this->quoteString(strtoupper($table)),
                $this->compileSchemaFilter('table_schema', $schema)
            ),
        };
    }

    /**
     * Compile the query to determine the indexes.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileIndexes($schema, $table)
    {
        return match ($this->platform()) {
            'luw' => sprintf(
                'select indname as "name", colnames as "columns", uniquerule as "rule" '
                .'from syscat.indexes where upper(tabname) = %s and %s',
                $this->quoteString(strtoupper($table)),
                $this->compileSchemaFilter('tabschema', $schema)
            ),
            'zos' => throw new RuntimeException('Retrieving indexes is not supported on DB2 for z/OS.'),
            default => sprintf(
                'select c.constraint_name as "name", '
                .'listagg(trim(k.column_name), \',\') within group (order by k.ordinal_position) as "columns", '
                .'case c.constraint_type when \'PRIMARY KEY\' then \'P\' else \'U\' end as "rule" '
                .'from qsys2.syscst c '
                .'join qsys2.syskeycst k on k.constraint_schema = c.constraint_schema and k.constraint_name = c.constraint_name '
                .'where c.constraint_type in (\'PRIMARY KEY\', \'UNIQUE\') and upper(c.table_name) = %s and %s '
                .'group by c.constraint_name, c.constraint_type '
                .'union all '
                .'select i.index_name as "name", '
                .'listagg(trim(k.column_name), \',\') within group (order by k.ordinal_position) as "columns", '
                .'case i.is_unique when \'U\' then \'U\' else \'D\' end as "rule" '
                .'from qsys2.sysindexes i '
                .'join qsys2.syskeys k on k.index_schema = i.index_schema and k.index_name = i.index_name '
                .'where upper(i.table_name) = %s and %s '
                .'group by i.index_name, i.is_unique',
                $this->quoteString(strtoupper($table)),
                $this->compileSchemaFilter('c.table_schema', $schema),
                $this->quoteString(strtoupper($table)),
                $this->compileSchemaFilter('i.table_schema', $schema)
            ),
        };
    }

    /**
     * Compile a create table command.
     *
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('create table %s (%s)',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        );
    }

    /**
     * Compile an add column command.
     *
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('alter table %s add column %s',
            $this->wrapTable($blueprint),
            $this->getColumn($blueprint, $command->column)
        );
    }

    /**
     * Compile a change column command.
     *
     * @return string
     */
    public function compileChange(Blueprint $blueprint, Fluent $command)
    {
        throw new RuntimeException('Changing columns is not supported by the DB2 driver. Use raw ALTER TABLE statements.');
    }

    /**
     * Compile a primary key command.
     *
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('alter table %s add constraint %s primary key (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a unique key command.
     *
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('alter table %s add constraint %s unique (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a plain index key command.
     *
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a drop table command.
     *
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * Requer DB2 LUW 11.1+ / IBM i 7.2+.
     *
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop column command.
     *
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = collect($command->columns)
            ->map(fn ($column) => 'drop column '.$this->wrap($column))
            ->implode(' ');

        return 'alter table '.$this->wrapTable($blueprint).' '.$columns;
    }

    /**
     * Compile a drop primary key command.
     *
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop primary key';
    }

    /**
     * Compile a drop unique key command.
     *
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a drop index command.
     *
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return 'drop index '.$this->wrap($command->index);
    }

    /**
     * Compile a drop foreign key command.
     *
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a rename table command.
     *
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('rename table %s to %s',
            $this->wrapTable($blueprint),
            $this->wrapTable($command->to)
        );
    }

    // -----------------------------------------------------------------
    //  Column types
    // -----------------------------------------------------------------

    protected function typeChar(Fluent $column)
    {
        return "char({$column->length})";
    }

    protected function typeString(Fluent $column)
    {
        return "varchar({$column->length})";
    }

    protected function typeTinyText(Fluent $column)
    {
        return 'varchar(255)';
    }

    protected function typeText(Fluent $column)
    {
        return 'clob';
    }

    protected function typeMediumText(Fluent $column)
    {
        return 'clob';
    }

    protected function typeLongText(Fluent $column)
    {
        return 'clob';
    }

    protected function typeInteger(Fluent $column)
    {
        return 'integer';
    }

    protected function typeBigInteger(Fluent $column)
    {
        return 'bigint';
    }

    protected function typeMediumInteger(Fluent $column)
    {
        return 'integer';
    }

    protected function typeSmallInteger(Fluent $column)
    {
        return 'smallint';
    }

    protected function typeTinyInteger(Fluent $column)
    {
        return 'smallint';
    }

    protected function typeFloat(Fluent $column)
    {
        return $column->precision ? "float({$column->precision})" : 'double';
    }

    protected function typeDouble(Fluent $column)
    {
        return 'double';
    }

    protected function typeDecimal(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }

    protected function typeBoolean(Fluent $column)
    {
        return 'smallint';
    }

    protected function typeEnum(Fluent $column)
    {
        return sprintf('varchar(255) check ("%s" in (%s))',
            $column->name,
            $this->quoteString($column->allowed)
        );
    }

    protected function typeJson(Fluent $column)
    {
        return 'clob';
    }

    protected function typeJsonb(Fluent $column)
    {
        return 'clob';
    }

    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    protected function typeDateTime(Fluent $column)
    {
        return $this->typeTimestamp($column);
    }

    protected function typeDateTimeTz(Fluent $column)
    {
        return $this->typeTimestamp($column);
    }

    protected function typeTime(Fluent $column)
    {
        return 'time';
    }

    protected function typeTimeTz(Fluent $column)
    {
        return 'time';
    }

    protected function typeTimestamp(Fluent $column)
    {
        return $column->precision !== null ? "timestamp({$column->precision})" : 'timestamp';
    }

    protected function typeTimestampTz(Fluent $column)
    {
        return $this->typeTimestamp($column);
    }

    protected function typeYear(Fluent $column)
    {
        return 'smallint';
    }

    protected function typeBinary(Fluent $column)
    {
        return 'blob';
    }

    protected function typeUuid(Fluent $column)
    {
        return 'char(36)';
    }

    protected function typeIpAddress(Fluent $column)
    {
        return 'varchar(45)';
    }

    protected function typeMacAddress(Fluent $column)
    {
        return 'varchar(17)';
    }

    // -----------------------------------------------------------------
    //  Column modifiers
    // -----------------------------------------------------------------

    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        return $column->nullable ? '' : ' not null';
    }

    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default);
        }
    }

    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' generated always as identity (start with 1 increment by 1) primary key';
        }
    }
}
