<?php
/**
 * Created by Son Pham
 * email: sp@outlook.com.vn
 * date: 05/13/2016
 */

class TQueryBuilder
{
    /**
     * @var array
     */
    private $quotes = ['\'', '"'];

    /**
     * @var array
     */
    private $bindingValues = [];

    /**
     * @var int
     */
    public static $count_bindingValues = 0;

    /**
     * @var string
     */
    private $sql = '';

    /**
     * @var string
     */
    private $lastStatement = '';

    /**
     * @return array
     */
    public function getBindingValues() {
        return $this->bindingValues;
    }

    /**
     * @return string
     */
    public function getSQL() {
        return $this->sql;
    }

    /**
     * reset sql builder
     */
    public function reset() {
        $this->sql = '';
        $this->bindingValues = [];
        //self::$count_bindingValues = 0;
        $this->lastStatement = '';
    }

    /**
     * @param int $value
     */
    private function increaseCount($value = 1) {
        self::$count_bindingValues += $value;
    }

    /**
     * @return int
     */
    private function getCount() {
        return self::$count_bindingValues;
    }

    /**
     * @param int $value
     * @return int
     */
    private function increaseAndGetCount($value = 1) {
        $this->increaseCount($value);
        return $this->getCount();
    }

    /**
     * append any text to current sql string
     *
     * @param string $text
     * @param bool $condition : add $text if $condition = true
     * @param bool $addSpace : add space char in front of $text if true
     * @return TQueryBuilder
     */
    private function append($text, $condition = true, $addSpace = true) {

        if ($condition === true) {
            if ($addSpace === true && (strlen($this->sql) > 0)) $this->sql .= ' ';
            $this->sql .= sprintf('%s', $text);
        }

        return $this;
    }

    /**
     * add value or field to current sql string
     *
     * @param string|TQueryBuilder|mixed $value
     * @param string $type = data|value (consider this), null, int number
     * @return TQueryBuilder
     */
    private function appendValue($value, $type = null) {

        if ($nestedSql = $this->nestedQuery($value)) $this->append($nestedSql);

        else {
            if (strcmp($type, 'data') === 0 || strcmp($type, 'value') === 0) {
                $key = ':value'.$this->increaseAndGetCount();
                $this->bindingValues[$key] = $value;
                $this->append($key);
            }
            else $this->append($value);
        }

        return $this;
    }

    /**
     * get sql string and import data binding if query is TQueryBuilder
     *
     * @param string|mixed|TQueryBuilder $query
     * @return mixed|string|bool
     */
    private function nestedQuery($query) {

        if ($query instanceof TQueryBuilder) {

            $this->importValuesBinding($query->getBindingValues());
            $result = sprintf('(%s)', $query->getSQL());

        } else $result = false;//trim($query);

        return $result;
    }

    /**
     * create columns string for select|group
     *
     * @param null|string|array $columns
     * @param bool $checkNestedQuery
     * @return string
     */
    private function columns($columns = null, $checkNestedQuery = true) {

        if (is_array($columns)) {
            if (count($columns) > 0) {
                $tmpCol = [];
                foreach ($columns as $alias => $value) {
                    if ($checkNestedQuery === true && $nestedSql = $this->nestedQuery($value)) {
                        $value = $nestedSql;
                    } elseif (in_array(substr($value, 0, 1), $this->quotes) && in_array(substr($value, -1), $this->quotes)) {
                        //neu $value co quote ' | " thi do la gia tri constant, can dua vao placeholder va value binding
                        $key = sprintf(':column%d', $this->increaseAndGetCount());
                        $this->bindingValues[$key] = trim($value, '\'"');
                        $value = $key;
                    }

                    if (is_int($alias)) $tmpCol[] = $value;
                    else $tmpCol[] = sprintf('%s AS %s', $value, $alias);
                }
                $col = implode(", ", $tmpCol);

            } else $col = '*';
        }
        elseif ($checkNestedQuery === true && $nestedSql = $this->nestedQuery($columns)) $col = $nestedSql;
        elseif (strlen($columns) === 0) $col = '*';
        elseif (in_array(substr($columns, 0, 1), $this->quotes) && in_array(substr($columns, -1), $this->quotes)) {
            //neu $value co quote ' | " thi do la gia tri constant, can dua vao placeholder va value binding
            $key = sprintf(':column%d', $this->increaseAndGetCount());
            $this->bindingValues[$key] = trim($columns, implode('',$this->quotes));
            $col = $key;
        }
        else $col = trim($columns);

        return $col;
    }

    /**
     * import values binding
     *
     * @param array $values
     */
    private function importValuesBinding(array $values = []) {
        $this->bindingValues = array_merge($this->bindingValues, $values);
    }

    /**
     * condition pair
     *
     * @param string $logic_name
     * @param array $fields [type => value | value]
     * @param string $operator =, >, LIKE, IS
     * @param bool $usedOpen
     * @return TQueryBuilder
     */
    private function condition($logic_name, array $fields = [], $operator = '=', $usedOpen = false) {

        $this->append($logic_name, strlen($logic_name) > 0)->append('(', $usedOpen);

        $count = count($fields);

        $types = array_keys($fields);
        $values = array_values($fields);

        if ($count > 0) {
            //consider first field 1
            $this->appendValue($values[0], $types[0]);

            //next field
            if ($count > 1) {
                //add operator
                $this->append($operator);
                $this->appendValue($values[1], $types[1]);
            }
        }

        return $this;
    }

    /**
     * create new TQueryBuilder object
     *
     * @return TQueryBuilder
     */
    public static function newQuery() {
        return new TQueryBuilder();
    }

    /**
     * @param string $key
     * @param string $values
     * @return TQueryBuilder
     */
    public function setBindingValue($key = '', $values = '') {
        if (strlen($key) > 0) $this->bindingValues[$key] = $values;
        return $this;
    }


    /**
     * open group (
     *
     * @param bool $condition
     * @param bool $addSpace
     * @return TQueryBuilder
     */
    public function open($condition = true, $addSpace = true) {
        return $this->append('(', $condition, $addSpace);
    }

    /**
     * close group )
     *
     * @param bool $condition
     * @param bool $addSpace
     * @return TQueryBuilder
     */
    public function close($condition = true, $addSpace = true) {
        return $this->append(')', $condition, $addSpace);
    }

    /**
     * @param null|array|string $columns : [alias => column (mixed|TQueryBuilder)]
     * @param bool $distinct
     * @return TQueryBuilder
     */
    public function select($columns = null, $distinct = false) {

        if (strcmp($this->lastStatement, 'SELECT') === 0) $this->append(",", true, false);
        else $this->append('SELECT');

        $this->lastStatement = 'SELECT';

        $this->append('DISTINCT', $distinct)->append($this->columns($columns));

        return $this;
    }

    /**
     * @param string|mixed|TQueryBuilder $table
     * @param string $alias
     * @return TQueryBuilder
     */
    public function from($table, $alias = null) {

        if (strcmp($this->lastStatement, 'FROM') === 0) $this->append(",", true, false);
        else $this->append('FROM');

        $this->appendValue($table)->append('AS '.$alias, strlen($alias) > 0);

        $this->lastStatement = 'FROM';
        return $this;
    }

    /**
     * @param string|mixed|TQueryBuilder $table
     * @param null|string $alias
     * @param null|string $type : LEFT, RIGHT, INNER, FULL OUTER, LEFT OUTER
     * @return TQueryBuilder
     */
    public function join($table, $alias = null, $type = null) {

        $this->append($type, strlen($type) > 0)->append('JOIN')->appendValue($table)
            ->append('AS '.$alias, strlen($alias) > 0);
        
        $this->lastStatement = 'JOIN';
        return $this;
    }

    /**
     * conditions of JOIN
     *
     * @param array $fields [value | type => value] type = data|value
     * @param string $operator = > >=, <= !=
     * @param bool $usedOpen
     * @return TQueryBuilder
     */
    public function on(array $fields = [], $operator = '=', $usedOpen = false) {

        $this->condition('ON', $fields, $operator, $usedOpen);

        $this->lastStatement = 'ON';

        return $this;
    }

    /**
     * AND condition
     *
     * @param array $fields [value | type => value] type = data|value
     * @param string $operator = > >=, <= !=
     * @param bool $usedOpen
     * @return TQueryBuilder
     */
    public function and_(array $fields = [], $operator = '=', $usedOpen = false) {

        $this->condition('AND', $fields, $operator, $usedOpen);

        $this->lastStatement = 'AND';

        return $this;
    }

    /**
     * OR condition
     *
     * @param array $fields [value | type => value] type = data|value
     * @param string $operator = > >=, <= !=
     * @param bool $usedOpen
     * @return TQueryBuilder
     */
    public function or_(array $fields = [], $operator = '=', $usedOpen = false) {

        $this->condition('OR', $fields, $operator, $usedOpen);

        $this->lastStatement = 'OR';

        return $this;
    }

    /**
     * WHERE condition
     *
     * @param array $fields [value | type => value] type = data|value
     * @param string $operator = > >=, <= != IS IN LIKE
     * @param bool $usedOpen
     * @return TQueryBuilder
     */
    public function where(array $fields = [], $operator = '=', $usedOpen = false) {

        $this->condition('WHERE', $fields, $operator, $usedOpen);

        $this->lastStatement = 'WHERE';

        return $this;
    }

    /**
     * GROUP BY columns
     *
     * @param string|array $columns
     * @return TQueryBuilder
     */
    public function group_by($columns) {

        if (strcmp($this->lastStatement, 'GROUP') === 0) $this->append(",", true, false);
        else $this->append('GROUP BY');

        $this->append($this->columns($columns, false));

        $this->lastStatement = 'GROUP';

        return $this;
    }

    /**
     * limit number
     *
     * @param int $number
     * @return TQueryBuilder
     */
    public function limit($number = 10) {

        $this->append('LIMIT '.$number * 1);

        $this->lastStatement = 'LIMIT';

        return $this;
    }

    /**
     * offset number
     *
     * @param int $number
     * @return TQueryBuilder
     */
    public function offset($number = 10) {

        $this->append('OFFSET '.$number * 1);

        $this->lastStatement = 'OFFSET';

        return $this;
    }

    /**
     * limit $number offset $from (informal is limit $number, $offset)
     *
     * @param int $number
     * @param int $from
     * @return TQueryBuilder
     */
    public function limit_offset($number = 10, $from = 10) {

        $this->limit($number)->offset($from);

        return $this;

    }

    /**
     * having
     *
     * @param array $fields [value | type => value] type = data|value
     * @param string $operator = > >=, <= !=
     * @param bool $usedOpen
     * @return TQueryBuilder
     */
    public function having(array $fields = [], $operator = '=', $usedOpen = false) {

        $this->condition('HAVING', $fields, $operator, $usedOpen);

        $this->lastStatement = 'HAVING';

        return $this;
    }

    /**
     * order by $column ASC|DESC
     *
     * @param string|array $columns : [column => direction]
     * @return TQueryBuilder
     */
    public function order_by($columns) {

        if (strcmp($this->lastStatement, 'ORDER') === 0) $this->append(",", true, false);
        else $this->append('ORDER BY');

        if (is_array($columns)) {
            $tmpCol = [];
            foreach ($columns as $column => $direction) {
                if (is_int($column)) $tmpCol[] = $direction;
                else $tmpCol[] = sprintf('%s %s', $column, $direction);
            }
            $this->append(implode(', ',$tmpCol));
        } else {
            $this->append(trim($columns));
        }

        $this->lastStatement = 'ORDER';

        return $this;

    }

    /**
     * UNION,combine 2 select queries
     *
     * @param bool $all
     * @return TQueryBuilder
     */
    public function union($all = false) {

        $this->append('UNION')->append('ALL', $all);

        $this->lastStatement = 'UNION';

        return $this;

    }

    /**
     * insert data to table from value or from select other table
     *
     * @param string $table
     * @param array $columns [column => value]
     * @param bool $fromTable insert data from select query
     * @return TQueryBuilder
     */
    public function insert($table, $columns = [], $fromTable = false) {

        if ($fromTable === true) {

            $fields = [];

            foreach ($columns as $field => $value) {
                if (is_int($field)) $fields[] = $value;
                else $fields[] = $field;
            }

            $this->append(sprintf('INSERT INTO %s(%s)', $table, implode(', ', $fields)));

        } else {

            $fields = $placeholders = [];

            foreach ($columns as $field => $value) {
                $fields[] = $field;
                $key = ":insert".$this->increaseAndGetCount();
                $this->bindingValues[$key] = $value;
                $placeholders[] = $key;
            }

            $this->append(sprintf('INSERT INTO %s(%s) VALUES (%s)', $table, implode(', ', $fields), implode(', ', $placeholders)));

        }

        $this->lastStatement = 'INSERT';

        return $this;

    }

    /**
     * @param string $table
     * @param array $columns
     * @return TQueryBuilder
     */
    public function update($table, $columns = []) {

        $this->append('UPDATE')->append($table)->append('SET');

        $cols = [];
        foreach ($columns as $column => $value) {
            if ($nestedSql = $this->nestedQuery($value)) {
                $cols[] = sprintf('%s = %s', $column, $nestedSql);
            }
            else {
                $key = ":update".$this->increaseAndGetCount();
                $this->bindingValues[$key] = $value;
                $cols[] = sprintf('%s = %s', $column, $key);
            }
        }

        $this->append(implode(', ', $cols));

        $this->lastStatement = 'UPDATE';

        return $this;

    }

    /**
     * @param string $table
     * @return TQueryBuilder
     */
    public function delete($table) {

        $this->append('DELETE FROM')->append($table);

        $this->lastStatement = 'DELETE';

        return $this;

    }

}

?>
