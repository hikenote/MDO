<?php
namespace MDO;

/**
 * 
 * @author shen2
 * 
 * 从Db_Adapter移动了fetch系列方法到此，
 * 
 * 优化了以下方法
 * fetchAssoc
 * fetchPairs
 * 
 * 增加了以下方法
 * fetchInto
 * fetchClass
 * fetchFunc
 * 
 * 删除了_getDummyTable()和相关代码
 * 删除了$_tableCols
 */
class Select extends Query
{
	const DISTINCT	   = 'distinct';
	const COLUMNS		= 'columns';
	const FROM		   = 'from';
	const UNION		  = 'union';
	const INDEX		  = 'index';
	const WHERE		  = 'where';
	const GROUP		  = 'group';
	const HAVING		 = 'having';
	const ORDER		  = 'order';
	const LIMIT_COUNT	= 'limitcount';
	const LIMIT_OFFSET   = 'limitoffset';
	const FOR_UPDATE	 = 'forupdate';

	const INNER_JOIN	 = 'inner join';
	const LEFT_JOIN	  = 'left join';
	const RIGHT_JOIN	 = 'right join';
	const FULL_JOIN	  = 'full join';
	const CROSS_JOIN	 = 'cross join';
	const NATURAL_JOIN   = 'natural join';

	const SQL_WILDCARD   = '*';
	const SQL_SELECT	 = 'SELECT';
	const SQL_UNION	  = 'UNION';
	const SQL_UNION_ALL  = 'UNION ALL';
	const SQL_FROM	   = 'FROM';
	const SQL_WHERE	  = 'WHERE';
	const SQL_FORCE_INDEX= 'FORCE INDEX';
	const SQL_DISTINCT   = 'DISTINCT';
	const SQL_GROUP_BY   = 'GROUP BY';
	const SQL_ORDER_BY   = 'ORDER BY';
	const SQL_HAVING	 = 'HAVING';
	const SQL_FOR_UPDATE = 'FOR UPDATE';
	const SQL_AND		= 'AND';
	const SQL_AS		 = 'AS';
	const SQL_OR		 = 'OR';
	const SQL_ON		 = 'ON';
	const SQL_ASC		= 'ASC';
	const SQL_DESC	   = 'DESC';
	
	/**
	 * Bind variables for query
	 *
	 * @var array
	 */
	protected $_bind = array();

	/**
	 * The initial values for the $_parts array.
	 * NOTE: It is important for the 'FOR_UPDATE' part to be last to ensure
	 * meximum compatibility with database adapters.
	 *
	 * @var array
	 */
	protected static $_partsInit = array(
		self::DISTINCT	 => false,
		self::COLUMNS	  => array(),
		self::UNION		=> array(),
		self::FROM		 => array(),
		self::INDEX		=> array(),
		self::WHERE		=> array(),
		self::GROUP		=> array(),
		self::HAVING	   => array(),
		self::ORDER		=> array(),
		self::LIMIT_COUNT  => null,
		self::LIMIT_OFFSET => null,
		self::FOR_UPDATE   => false
	);

	/**
	 * Specify legal join types.
	 *
	 * @var array
	 */
	protected static $_joinTypes = array(
		self::INNER_JOIN,
		self::LEFT_JOIN,
		self::RIGHT_JOIN,
		self::FULL_JOIN,
		self::CROSS_JOIN,
		self::NATURAL_JOIN,
	);

	/**
	 * Specify legal union types.
	 *
	 * @var array
	 */
	protected static $_unionTypes = array(
		self::SQL_UNION,
		self::SQL_UNION_ALL
	);

	/**
	 * The component parts of a SELECT statement.
	 * Initialized to the $_partsInit array in the constructor.
	 *
	 * @var array
	 */
	protected $_parts = array();

	/**
	 * Class constructor
	 *
	 * @param Adapter $adapter
	 */
	public function __construct(Adapter $adapter)
	{
		$this->_adapter = $adapter;
		$this->_parts = self::$_partsInit;
	}

	/**
	 * Get bind variables
	 *
	 * @return array
	 */
	public function getBind()
	{
		return $this->_bind;
	}

	/**
	 * Set bind variables
	 *
	 * @param mixed $bind
	 * @return Select
	 */
	public function bind($bind)
	{
		$this->_bind = $bind;

		return $this;
	}

	/**
	 * Makes the query SELECT DISTINCT.
	 *
	 * @param bool $flag Whether or not the SELECT is DISTINCT (default true).
	 * @return Select This Select object.
	 */
	public function distinct($flag = true)
	{
		$this->_parts[self::DISTINCT] = (bool) $flag;
		return $this;
	}

	/**
	 * Adds a FROM table and optional columns to the query.
	 *
	 * The first parameter $name can be a simple string, in which case the
	 * correlation name is generated automatically.  If you want to specify
	 * the correlation name, the first parameter must be an associative
	 * array in which the key is the correlation name, and the value is
	 * the physical table name.  For example, array('alias' => 'table').
	 * The correlation name is prepended to all columns fetched for this
	 * table.
	 *
	 * The second parameter can be a single string or Expr object,
	 * or else an array of strings or Expr objects.
	 *
	 * The first parameter can be null or an empty string, in which case
	 * no correlation name is generated or prepended to the columns named
	 * in the second parameter.
	 *
	 * @param  array|string|Expr $name The table name or an associative array
	 *										 relating correlation name to table name.
	 * @param  array|string|Expr $cols The columns to select from this table.
	 * @param  string $schema The schema name to specify, if any.
	 * @return Select This Select object.
	 */
	public function from($name, $cols = '*', $schema = null)
	{
		return $this->_join(self::FROM, $name, null, $cols, $schema);
	}

	/**
	 * Specifies the columns used in the FROM clause.
	 *
	 * The parameter can be a single string or Expr object,
	 * or else an array of strings or Expr objects.
	 *
	 * @param  array|string|Expr $cols The columns to select from this table.
	 * @param  string $correlationName Correlation name of target table. OPTIONAL
	 * @return Select This Select object.
	 */
	public function columns($cols = '*', $correlationName = null)
	{
		if ($correlationName === null && count($this->_parts[self::FROM])) {
			$correlationNameKeys = array_keys($this->_parts[self::FROM]);
			$correlationName = current($correlationNameKeys);
		}

		if (!array_key_exists($correlationName, $this->_parts[self::FROM])) {
			throw new SelectException("No table has been specified for the FROM clause");
		}

		$this->_tableCols($correlationName, $cols);

		return $this;
	}

	/**
	 * Adds a UNION clause to the query.
	 *
	 * The first parameter has to be an array of Select or
	 * sql query strings.
	 *
	 * <code>
	 * $sql1 = $db->select();
	 * $sql2 = "SELECT ...";
	 * $select = $db->select()
	 *	  ->union(array($sql1, $sql2))
	 *	  ->order("id");
	 * </code>
	 *
	 * @param  array $select Array of select clauses for the union.
	 * @return Select This Select object.
	 */
	public function union($select = array(), $type = self::SQL_UNION)
	{
		if (!is_array($select)) {
			throw new SelectException(
				"union() only accepts an array of Select instances of sql query strings."
			);
		}

		if (!in_array($type, self::$_unionTypes)) {
			throw new SelectException("Invalid union type '{$type}'");
		}

		foreach ($select as $target) {
			$this->_parts[self::UNION][] = array($target, $type);
		}

		return $this;
	}

	/**
	 * Adds a JOIN table and columns to the query.
	 *
	 * The $name and $cols parameters follow the same logic
	 * as described in the from() method.
	 *
	 * @param  array|string|Expr $name The table name.
	 * @param  string $cond Join on this condition.
	 * @param  array|string $cols The columns to select from the joined table.
	 * @param  string $schema The database name to specify, if any.
	 * @return Select This Select object.
	 */
	public function join($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
	{
		return $this->joinInner($name, $cond, $cols, $schema);
	}

	/**
	 * Add an INNER JOIN table and colums to the query
	 * Rows in both tables are matched according to the expression
	 * in the $cond argument.  The result set is comprised
	 * of all cases where rows from the left table match
	 * rows from the right table.
	 *
	 * The $name and $cols parameters follow the same logic
	 * as described in the from() method.
	 *
	 * @param  array|string|Expr $name The table name.
	 * @param  string $cond Join on this condition.
	 * @param  array|string $cols The columns to select from the joined table.
	 * @param  string $schema The database name to specify, if any.
	 * @return Select This Select object.
	 */
	public function joinInner($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
	{
		return $this->_join(self::INNER_JOIN, $name, $cond, $cols, $schema);
	}

	/**
	 * Add a LEFT OUTER JOIN table and colums to the query
	 * All rows from the left operand table are included,
	 * matching rows from the right operand table included,
	 * and the columns from the right operand table are filled
	 * with NULLs if no row exists matching the left table.
	 *
	 * The $name and $cols parameters follow the same logic
	 * as described in the from() method.
	 *
	 * @param  array|string|Expr $name The table name.
	 * @param  string $cond Join on this condition.
	 * @param  array|string $cols The columns to select from the joined table.
	 * @param  string $schema The database name to specify, if any.
	 * @return Select This Select object.
	 */
	public function joinLeft($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
	{
		return $this->_join(self::LEFT_JOIN, $name, $cond, $cols, $schema);
	}

	/**
	 * Add a RIGHT OUTER JOIN table and colums to the query.
	 * Right outer join is the complement of left outer join.
	 * All rows from the right operand table are included,
	 * matching rows from the left operand table included,
	 * and the columns from the left operand table are filled
	 * with NULLs if no row exists matching the right table.
	 *
	 * The $name and $cols parameters follow the same logic
	 * as described in the from() method.
	 *
	 * @param  array|string|Expr $name The table name.
	 * @param  string $cond Join on this condition.
	 * @param  array|string $cols The columns to select from the joined table.
	 * @param  string $schema The database name to specify, if any.
	 * @return Select This Select object.
	 */
	public function joinRight($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
	{
		return $this->_join(self::RIGHT_JOIN, $name, $cond, $cols, $schema);
	}

	/**
	 * Add a FULL OUTER JOIN table and colums to the query.
	 * A full outer join is like combining a left outer join
	 * and a right outer join.  All rows from both tables are
	 * included, paired with each other on the same row of the
	 * result set if they satisfy the join condition, and otherwise
	 * paired with NULLs in place of columns from the other table.
	 *
	 * The $name and $cols parameters follow the same logic
	 * as described in the from() method.
	 *
	 * @param  array|string|Expr $name The table name.
	 * @param  string $cond Join on this condition.
	 * @param  array|string $cols The columns to select from the joined table.
	 * @param  string $schema The database name to specify, if any.
	 * @return Select This Select object.
	 */
	public function joinFull($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
	{
		return $this->_join(self::FULL_JOIN, $name, $cond, $cols, $schema);
	}

	/**
	 * Add a CROSS JOIN table and colums to the query.
	 * A cross join is a cartesian product; there is no join condition.
	 *
	 * The $name and $cols parameters follow the same logic
	 * as described in the from() method.
	 *
	 * @param  array|string|Expr $name The table name.
	 * @param  array|string $cols The columns to select from the joined table.
	 * @param  string $schema The database name to specify, if any.
	 * @return Select This Select object.
	 */
	public function joinCross($name, $cols = self::SQL_WILDCARD, $schema = null)
	{
		return $this->_join(self::CROSS_JOIN, $name, null, $cols, $schema);
	}

	/**
	 * Add a NATURAL JOIN table and colums to the query.
	 * A natural join assumes an equi-join across any column(s)
	 * that appear with the same name in both tables.
	 * Only natural inner joins are supported by this API,
	 * even though SQL permits natural outer joins as well.
	 *
	 * The $name and $cols parameters follow the same logic
	 * as described in the from() method.
	 *
	 * @param  array|string|Expr $name The table name.
	 * @param  array|string $cols The columns to select from the joined table.
	 * @param  string $schema The database name to specify, if any.
	 * @return Select This Select object.
	 */
	public function joinNatural($name, $cols = self::SQL_WILDCARD, $schema = null)
	{
		return $this->_join(self::NATURAL_JOIN, $name, null, $cols, $schema);
	}
	
	public function forceIndex($indexList, $for = null){
		$this->_parts[self::INDEX][] = self::SQL_FORCE_INDEX . ($for ? ' FOR ' . $for : '') . ' (' . implode(',', (array) $indexList) . ')';
		return $this;
	}

	/**
	 * Adds a WHERE condition to the query by AND.
	 *
	 * If a value is passed as the second param, it will be quoted
	 * and replaced into the condition wherever a question-mark
	 * appears. Array values are quoted and comma-separated.
	 *
	 * <code>
	 * // simplest but non-secure
	 * $select->where("id = $id");
	 *
	 * // secure (ID is quoted but matched anyway)
	 * $select->where('id = ?', $id);
	 *
	 * // alternatively, with named binding
	 * $select->where('id = :id');
	 * </code>
	 *
	 * Note that it is more correct to use named bindings in your
	 * queries for values other than strings. When you use named
	 * bindings, don't forget to pass the values when actually
	 * making a query:
	 *
	 * <code>
	 * $db->fetchAll($select, array('id' => 5));
	 * </code>
	 *
	 * @param string   $cond  The WHERE condition.
	 * @param mixed	$value OPTIONAL The value to quote into the condition.
	 * @param int	  $type  OPTIONAL The type of the given value
	 * @return Select This Select object.
	 */
	public function where($cond, $value = null, $type = null)
	{
		$this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, true);

		return $this;
	}

	/**
	 * Adds a WHERE condition to the query by OR.
	 *
	 * Otherwise identical to where().
	 *
	 * @param string   $cond  The WHERE condition.
	 * @param mixed	$value OPTIONAL The value to quote into the condition.
	 * @param int	  $type  OPTIONAL The type of the given value
	 * @return Select This Select object.
	 *
	 * @see where()
	 */
	public function orWhere($cond, $value = null, $type = null)
	{
		$this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, false);

		return $this;
	}

	/**
	 * Adds grouping to the query.
	 *
	 * @param  array|string $spec The column(s) to group by.
	 * @return Select This Select object.
	 */
	public function group($spec)
	{
		if (!is_array($spec)) {
			$spec = array($spec);
		}

		foreach ($spec as $val) {
			if (preg_match('/\(.*\)/', (string) $val)) {
				$val = new Expr($val);
			}
			$this->_parts[self::GROUP][] = $val;
		}

		return $this;
	}

	/**
	 * Adds a HAVING condition to the query by AND.
	 *
	 * If a value is passed as the second param, it will be quoted
	 * and replaced into the condition wherever a question-mark
	 * appears. See {@link where()} for an example
	 *
	 * @param string $cond The HAVING condition.
	 * @param mixed	$value OPTIONAL The value to quote into the condition.
	 * @param int	  $type  OPTIONAL The type of the given value
	 * @return Select This Select object.
	 */
	public function having($cond, $value = null, $type = null)
	{
		if ($value !== null) {
			$cond = $this->_adapter->quoteInto($cond, $value, $type);
		}

		if ($this->_parts[self::HAVING]) {
			$this->_parts[self::HAVING][] = self::SQL_AND . " ($cond)";
		} else {
			$this->_parts[self::HAVING][] = "($cond)";
		}

		return $this;
	}

	/**
	 * Adds a HAVING condition to the query by OR.
	 *
	 * Otherwise identical to orHaving().
	 *
	 * @param string $cond The HAVING condition.
	 * @param mixed	$value OPTIONAL The value to quote into the condition.
	 * @param int	  $type  OPTIONAL The type of the given value
	 * @return Select This Select object.
	 *
	 * @see having()
	 */
	public function orHaving($cond, $value = null, $type = null)
	{
		if ($value !== null) {
			$cond = $this->_adapter->quoteInto($cond, $value, $type);
		}

		if ($this->_parts[self::HAVING]) {
			$this->_parts[self::HAVING][] = self::SQL_OR . " ($cond)";
		} else {
			$this->_parts[self::HAVING][] = "($cond)";
		}

		return $this;
	}

	/**
	 * Adds a row order to the query.
	 *
	 * @param mixed $spec The column(s) and direction to order by.
	 * @return Select This Select object.
	 */
	public function order($spec)
	{
		if (!is_array($spec)) {
			$spec = array($spec);
		}

		// force 'ASC' or 'DESC' on each order spec, default is ASC.
		foreach ($spec as $val) {
			if ($val instanceof Expr) {
				$expr = $val->__toString();
				if (empty($expr)) {
					continue;
				}
				$this->_parts[self::ORDER][] = $val;
			} else {
				if (empty($val)) {
					continue;
				}
				$direction = self::SQL_ASC;
				if (preg_match('/(.*\W)(' . self::SQL_ASC . '|' . self::SQL_DESC . ')\b/si', $val, $matches)) {
					$val = trim($matches[1]);
					$direction = $matches[2];
				}
				if (preg_match('/\(.*\)/', $val)) {
					$val = new Expr($val);
				}
				$this->_parts[self::ORDER][] = array($val, $direction);
			}
		}

		return $this;
	}

	/**
	 * Sets a limit count and offset to the query.
	 *
	 * @param int $count OPTIONAL The number of rows to return.
	 * @param int $offset OPTIONAL Start returning after this many rows.
	 * @return Select This Select object.
	 */
	public function limit($count = null, $offset = null)
	{
		$this->_parts[self::LIMIT_COUNT]  = (int) $count;
		$this->_parts[self::LIMIT_OFFSET] = (int) $offset;
		return $this;
	}

	/**
	 * Sets the limit and count by page number.
	 *
	 * @param int $page Limit results to this page number.
	 * @param int $rowCount Use this many rows per page.
	 * @return Select This Select object.
	 */
	public function limitPage($page, $rowCount)
	{
		$page	 = ($page > 0)	 ? $page	 : 1;
		$rowCount = ($rowCount > 0) ? $rowCount : 1;
		$this->_parts[self::LIMIT_COUNT]  = (int) $rowCount;
		$this->_parts[self::LIMIT_OFFSET] = (int) $rowCount * ($page - 1);
		return $this;
	}

	/**
	 * Makes the query SELECT FOR UPDATE.
	 *
	 * @param bool $flag Whether or not the SELECT is FOR UPDATE (default true).
	 * @return Select This Select object.
	 */
	public function forUpdate($flag = true)
	{
		$this->_parts[self::FOR_UPDATE] = (bool) $flag;
		return $this;
	}

	/**
	 * Get part of the structured information for the currect query.
	 *
	 * @param string $part
	 * @return mixed
	 * @throws SelectException
	 */
	public function getPart($part)
	{
		$part = strtolower($part);
		if (!array_key_exists($part, $this->_parts)) {
			throw new SelectException("Invalid Select part '$part'");
		}
		return $this->_parts[$part];
	}

	/**
	 * Executes the current select object and returns the result
	 *
	 * @param integer $fetchMode OPTIONAL
	 * @param  mixed  $bind An array of data to bind to the placeholders.
	 * @return \mysqli_stmt
	 */
	public function query($fetchMode = null, $bind = array())
	{
		if (!empty($bind)) {
			$this->bind($bind);
		}

		$stmt = $this->_adapter->query($this);
		if ($fetchMode == null) {
			$fetchMode = $this->_adapter->getFetchMode();
		}
		$stmt->setFetchMode($fetchMode);
		return $stmt;
	}

	/**
	 * Converts this object to an SQL SELECT string.
	 *
	 * @return string|null This object as a SELECT string. (or null if a string cannot be produced.)
	 */
	public function assemble()
	{
		$sql = self::SQL_SELECT;
		foreach (array_keys(self::$_partsInit) as $part) {
			$method = '_render' . ucfirst($part);
			if (method_exists($this, $method)) {
				$sql = $this->$method($sql);
			}
		}
		return $sql;
	}

	/**
	 * Clear parts of the Select object, or an individual part.
	 *
	 * @param string $part OPTIONAL
	 * @return Select
	 */
	public function reset($part = null)
	{
		if ($part == null) {
			$this->_parts = self::$_partsInit;
		} else if (array_key_exists($part, self::$_partsInit)) {
			$this->_parts[$part] = self::$_partsInit[$part];
		}
		return $this;
	}

	/**
	 * Gets the Adapter for this
	 * particular Select object.
	 *
	 * @return Adapter
	 */
	public function getAdapter()
	{
		return $this->_adapter;
	}

	/**
	 * Populate the {@link $_parts} 'join' key
	 *
	 * Does the dirty work of populating the join key.
	 *
	 * The $name and $cols parameters follow the same logic
	 * as described in the from() method.
	 *
	 * @param  null|string $type Type of join; inner, left, and null are currently supported
	 * @param  array|string|Expr $name Table name
	 * @param  string $cond Join on this condition
	 * @param  array|string $cols The columns to select from the joined table
	 * @param  string $schema The database name to specify, if any.
	 * @return Select This Select object
	 * @throws SelectException
	 */
	protected function _join($type, $name, $cond, $cols, $schema = null)
	{
		if (!in_array($type, self::$_joinTypes) && $type != self::FROM) {
			throw new SelectException("Invalid join type '$type'");
		}

		if (count($this->_parts[self::UNION])) {
			throw new SelectException("Invalid use of table with " . self::SQL_UNION);
		}

		if (empty($name)) {
			$correlationName = $tableName = '';
		} else if (is_array($name)) {
			// Must be array($correlationName => $tableName) or array($ident, ...)
			foreach ($name as $_correlationName => $_tableName) {
				if (is_string($_correlationName)) {
					// We assume the key is the correlation name and value is the table name
					$tableName = $_tableName;
					$correlationName = $_correlationName;
				} else {
					// We assume just an array of identifiers, with no correlation name
					$tableName = $_tableName;
					$correlationName = $this->_uniqueCorrelation($tableName);
				}
				break;
			}
		} else if ($name instanceof Expr|| $name instanceof Select) {
			$tableName = $name;
			$correlationName = $this->_uniqueCorrelation('t');
		} else if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $name, $m)) {
			$tableName = $m[1];
			$correlationName = $m[2];
		} else {
			$tableName = $name;
			$correlationName = $this->_uniqueCorrelation($tableName);
		}

		// Schema from table name overrides schema argument
		if (!is_object($tableName) && false !== strpos($tableName, '.')) {
			list($schema, $tableName) = explode('.', $tableName);
		}

		$lastFromCorrelationName = null;
		if (!empty($correlationName)) {
			if (array_key_exists($correlationName, $this->_parts[self::FROM])) {
				throw new SelectException("You cannot define a correlation name '$correlationName' more than once");
			}

			if ($type == self::FROM) {
				// append this from after the last from joinType
				$tmpFromParts = $this->_parts[self::FROM];
				$this->_parts[self::FROM] = array();
				// move all the froms onto the stack
				while ($tmpFromParts) {
					$currentCorrelationName = key($tmpFromParts);
					if ($tmpFromParts[$currentCorrelationName]['joinType'] != self::FROM) {
						break;
					}
					$lastFromCorrelationName = $currentCorrelationName;
					$this->_parts[self::FROM][$currentCorrelationName] = array_shift($tmpFromParts);
				}
			} else {
				$tmpFromParts = array();
			}
			$this->_parts[self::FROM][$correlationName] = array(
				'joinType'	  => $type,
				'schema'		=> $schema,
				'tableName'	 => $tableName,
				'joinCondition' => $cond
				);
			while ($tmpFromParts) {
				$currentCorrelationName = key($tmpFromParts);
				$this->_parts[self::FROM][$currentCorrelationName] = array_shift($tmpFromParts);
			}
		}

		// add to the columns from this joined table
		if ($type == self::FROM && $lastFromCorrelationName == null) {
			$lastFromCorrelationName = true;
		}
		$this->_tableCols($correlationName, $cols, $lastFromCorrelationName);

		return $this;
	}

	/**
	 * Handle JOIN... USING... syntax
	 *
	 * This is functionality identical to the existing JOIN methods, however
	 * the join condition can be passed as a single column name. This method
	 * then completes the ON condition by using the same field for the FROM
	 * table and the JOIN table.
	 *
	 * <code>
	 * $select = $db->select()->from('table1')
	 *						->joinUsing('table2', 'column1');
	 *
	 * // SELECT * FROM table1 JOIN table2 ON table1.column1 = table2.column2
	 * </code>
	 *
	 * These joins are called by the developer simply by adding 'Using' to the
	 * method name. E.g.
	 * * joinUsing
	 * * joinInnerUsing
	 * * joinFullUsing
	 * * joinRightUsing
	 * * joinLeftUsing
	 *
	 * @return Select This Select object.
	 */
	public function _joinUsing($type, $name, $cond, $cols = '*', $schema = null)
	{
		if (empty($this->_parts[self::FROM])) {
			throw new SelectException("You can only perform a joinUsing after specifying a FROM table");
		}

		$join  = $this->_adapter->quoteIdentifier(key($this->_parts[self::FROM]), true);
		$from  = $this->_adapter->quoteIdentifier($this->_uniqueCorrelation($name), true);

		$cond1 = $from . '.' . $cond;
		$cond2 = $join . '.' . $cond;
		$cond  = $cond1 . ' = ' . $cond2;

		return $this->_join($type, $name, $cond, $cols, $schema);
	}

	/**
	 * Generate a unique correlation name
	 *
	 * @param string|array $name A qualified identifier.
	 * @return string A unique correlation name.
	 */
	private function _uniqueCorrelation($name)
	{
		if (is_array($name)) {
			$c = end($name);
		} else {
			// Extract just the last name of a qualified table name
			$dot = strrpos($name,'.');
			$c = ($dot === false) ? $name : substr($name, $dot+1);
		}
		for ($i = 2; array_key_exists($c, $this->_parts[self::FROM]); ++$i) {
			$c = $name . '_' . (string) $i;
		}
		return $c;
	}

	/**
	 * Adds to the internal table-to-column mapping array.
	 *
	 * @param  string $tbl The table/join the columns come from.
	 * @param  array|string $cols The list of columns; preferably as
	 * an array, but possibly as a string containing one column.
	 * @param  bool|string True if it should be prepended, a correlation name if it should be inserted
	 * @return void
	 */
	protected function _tableCols($correlationName, $cols, $afterCorrelationName = null)
	{
		if (!is_array($cols)) {
			$cols = array($cols);
		}

		if ($correlationName == null) {
			$correlationName = '';
		}

		$columnValues = array();

		foreach (array_filter($cols) as $alias => $col) {
			$currentCorrelationName = $correlationName;
			if (is_string($col)) {
				// Check for a column matching "<column> AS <alias>" and extract the alias name
				if (preg_match('/^(.+)\s+' . self::SQL_AS . '\s+(.+)$/i', $col, $m)) {
					$col = $m[1];
					$alias = $m[2];
				}
				// Check for columns that look like functions and convert to Expr
				if (preg_match('/\(.*\)/', $col)) {
					$col = new Expr($col);
				} elseif (preg_match('/(.+)\.(.+)/', $col, $m)) {
					$currentCorrelationName = $m[1];
					$col = $m[2];
				}
			}
			$columnValues[] = array($currentCorrelationName, $col, is_string($alias) ? $alias : null);
		}

		if ($columnValues) {

			// should we attempt to prepend or insert these values?
			if ($afterCorrelationName === true || is_string($afterCorrelationName)) {
				$tmpColumns = $this->_parts[self::COLUMNS];
				$this->_parts[self::COLUMNS] = array();
			} else {
				$tmpColumns = array();
			}

			// find the correlation name to insert after
			if (is_string($afterCorrelationName)) {
				while ($tmpColumns) {
					$this->_parts[self::COLUMNS][] = $currentColumn = array_shift($tmpColumns);
					if ($currentColumn[0] == $afterCorrelationName) {
						break;
					}
				}
			}

			// apply current values to current stack
			foreach ($columnValues as $columnValue) {
				array_push($this->_parts[self::COLUMNS], $columnValue);
			}

			// finish ensuring that all previous values are applied (if they exist)
			while ($tmpColumns) {
				array_push($this->_parts[self::COLUMNS], array_shift($tmpColumns));
			}
		}
	}

	/**
	 * Internal function for creating the where clause
	 *
	 * @param string   $condition
	 * @param mixed	$value  optional
	 * @param string   $type   optional
	 * @param boolean  $bool  true = AND, false = OR
	 * @return string  clause
	 */
	protected function _where($condition, $value = null, $type = null, $bool = true)
	{
		if (count($this->_parts[self::UNION])) {
			throw new SelectException("Invalid use of where clause with " . self::SQL_UNION);
		}

		if ($value !== null) {
			$condition = $this->_adapter->quoteInto($condition, $value, $type);
		}

		$cond = "";
		if ($this->_parts[self::WHERE]) {
			if ($bool === true) {
				$cond = self::SQL_AND . ' ';
			} else {
				$cond = self::SQL_OR . ' ';
			}
		}

		return $cond . "($condition)";
	}

	/**
	 * Return a quoted schema name
	 *
	 * @param string   $schema  The schema name OPTIONAL
	 * @return string|null
	 */
	protected function _getQuotedSchema($schema = null)
	{
		if ($schema === null) {
			return null;
		}
		return $this->_adapter->quoteIdentifier($schema, true) . '.';
	}

	/**
	 * Return a quoted table name
	 *
	 * @param string   $tableName		The table name
	 * @param string   $correlationName  The correlation name OPTIONAL
	 * @return string
	 */
	protected function _getQuotedTable($tableName, $correlationName = null)
	{
		return $this->_adapter->quoteTableAs($tableName, $correlationName, true);
	}

	/**
	 * Render DISTINCT clause
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderDistinct($sql)
	{
		if ($this->_parts[self::DISTINCT]) {
			$sql .= ' ' . self::SQL_DISTINCT;
		}

		return $sql;
	}

	/**
	 * Render DISTINCT clause
	 *
	 * @param string   $sql SQL query
	 * @return string|null
	 */
	protected function _renderColumns($sql)
	{
		if (!count($this->_parts[self::COLUMNS])) {
			return null;
		}

		$columns = array();
		foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
			list($correlationName, $column, $alias) = $columnEntry;
			if ($column instanceof Expr) {
				$columns[] = $this->_adapter->quoteColumnAs($column, $alias, true);
			} else {
				if ($column == self::SQL_WILDCARD) {
					$column = new Expr(self::SQL_WILDCARD);
					$alias = null;
				}
				if (empty($correlationName)) {
					$columns[] = $this->_adapter->quoteColumnAs($column, $alias, true);
				} else {
					$columns[] = $this->_adapter->quoteColumnAs(array($correlationName, $column), $alias, true);
				}
			}
		}

		return $sql .= ' ' . implode(', ', $columns);
	}

	/**
	 * Render FROM clause
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderFrom($sql)
	{
		$from = array();

		foreach ($this->_parts[self::FROM] as $correlationName => $table) {
			$tmp = '';

			$joinType = ($table['joinType'] == self::FROM) ? self::INNER_JOIN : $table['joinType'];

			// Add join clause (if applicable)
			if (! empty($from)) {
				$tmp .= ' ' . strtoupper($joinType) . ' ';
			}

			$tmp .= $this->_getQuotedSchema($table['schema']);
			$tmp .= $this->_getQuotedTable($table['tableName'], $correlationName);

			// Add join conditions (if applicable)
			if (!empty($from) && ! empty($table['joinCondition'])) {
				$tmp .= ' ' . self::SQL_ON . ' ' . $table['joinCondition'];
			}

			// Add the table name and condition add to the list
			$from[] = $tmp;
		}

		// Add the list of all joins
		if (!empty($from)) {
			$sql .= ' ' . self::SQL_FROM . ' ' . implode("\n", $from);
		}

		return $sql;
	}

	/**
	 * Render UNION query
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderUnion($sql)
	{
		if ($this->_parts[self::UNION]) {
			$parts = count($this->_parts[self::UNION]);
			foreach ($this->_parts[self::UNION] as $cnt => $union) {
				list($target, $type) = $union;
				if ($target instanceof Select) {
					$target = $target->assemble();
				}
				$sql .= $target;
				if ($cnt < $parts - 1) {
					$sql .= ' ' . $type . ' ';
				}
			}
		}

		return $sql;
	}
	
	protected function _renderIndex($sql){
		foreach ($this->_parts[self::INDEX] as $part) {
			$sql .= ' ' . $part;
		}
		
		return $sql;
	}

	/**
	 * Render WHERE clause
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderWhere($sql)
	{
		if ($this->_parts[self::FROM] && $this->_parts[self::WHERE]) {
			$sql .= ' ' . self::SQL_WHERE . ' ' .  implode(' ', $this->_parts[self::WHERE]);
		}

		return $sql;
	}

	/**
	 * Render GROUP clause
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderGroup($sql)
	{
		if ($this->_parts[self::FROM] && $this->_parts[self::GROUP]) {
			$group = array();
			foreach ($this->_parts[self::GROUP] as $term) {
				$group[] = $this->_adapter->quoteIdentifier($term, true);
			}
			$sql .= ' ' . self::SQL_GROUP_BY . ' ' . implode(",\n\t", $group);
		}

		return $sql;
	}

	/**
	 * Render HAVING clause
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderHaving($sql)
	{
		if ($this->_parts[self::FROM] && $this->_parts[self::HAVING]) {
			$sql .= ' ' . self::SQL_HAVING . ' ' . implode(' ', $this->_parts[self::HAVING]);
		}

		return $sql;
	}

	/**
	 * Render ORDER clause
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderOrder($sql)
	{
		if ($this->_parts[self::ORDER]) {
			$order = array();
			foreach ($this->_parts[self::ORDER] as $term) {
				if (is_array($term)) {
					if(is_numeric($term[0]) && strval(intval($term[0])) == $term[0]) {
						$order[] = (int)trim($term[0]) . ' ' . $term[1];
					} else {
						$order[] = $this->_adapter->quoteIdentifier($term[0], true) . ' ' . $term[1];
					}
				} else if (is_numeric($term) && strval(intval($term)) == $term) {
					$order[] = (int)trim($term);
				} else {
					$order[] = $this->_adapter->quoteIdentifier($term, true);
				}
			}
			$sql .= ' ' . self::SQL_ORDER_BY . ' ' . implode(', ', $order);
		}

		return $sql;
	}

	/**
	 * Render LIMIT OFFSET clause
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderLimitoffset($sql)
	{
		$count = 0;
		$offset = 0;

		if (!empty($this->_parts[self::LIMIT_OFFSET])) {
			$offset = (int) $this->_parts[self::LIMIT_OFFSET];
			$count = PHP_INT_MAX;
		}

		if (!empty($this->_parts[self::LIMIT_COUNT])) {
			$count = (int) $this->_parts[self::LIMIT_COUNT];
		}

		/*
		 * Add limits clause
		 * 从adapter中移动过来
		 */
		if ($count > 0) {
			$sql .= " LIMIT $count";
			if ($offset > 0) {
				$sql .= " OFFSET $offset";
			}
		}

		return $sql;
	}

	/**
	 * Render FOR UPDATE clause
	 *
	 * @param string   $sql SQL query
	 * @return string
	 */
	protected function _renderForupdate($sql)
	{
		if ($this->_parts[self::FOR_UPDATE]) {
			$sql .= ' ' . self::SQL_FOR_UPDATE;
		}

		return $sql;
	}

	/**
	 * Turn magic function calls into non-magic function calls
	 * for joinUsing syntax
	 *
	 * @param string $method
	 * @param array $args OPTIONAL TableSelect query modifier
	 * @return Select
	 * @throws SelectException If an invalid method is called.
	 */
	public function __call($method, array $args)
	{
		$matches = array();

		/**
		 * Recognize methods for Has-Many cases:
		 * findParent<Class>()
		 * findParent<Class>By<Rule>()
		 * Use the non-greedy pattern repeat modifier e.g. \w+?
		 */
		if (preg_match('/^join([a-zA-Z]*?)Using$/', $method, $matches)) {
			$type = strtolower($matches[1]);
			if ($type) {
				$type .= ' join';
				if (!in_array($type, self::$_joinTypes)) {
					throw new SelectException("Unrecognized method '$method()'");
				}
				if (in_array($type, array(self::CROSS_JOIN, self::NATURAL_JOIN))) {
					throw new SelectException("Cannot perform a joinUsing with method '$method()'");
				}
			} else {
				$type = self::INNER_JOIN;
			}
			array_unshift($args, $type);
			return call_user_func_array(array($this, '_joinUsing'), $args);
		}

		throw new SelectException("Unrecognized method '$method()'");
	}

	/**
	 * Implements magic method.
	 *
	 * @return string This object as a SELECT string.
	 */
	public function __toString()
	{
		try {
			$sql = $this->assemble();
		} catch (Exception $e) {
			trigger_error($e->getMessage(), E_USER_WARNING);
			$sql = '';
		}
		return (string)$sql;
	}
	
	
	/************* 原生select方法结束，以下是从Db_Adapter移动过来的fetch方法库 ************/
	
	/**
	 * Fetches all SQL result rows as a sequential array.
	 * Uses the current fetchMode for the adapter.
	 *
	 * @param mixed				 $fetchMode Override current fetch mode.
	 * @return Statement
	 */
	public function fetchAll($fetchMode = null)
	{
		if ($fetchMode === null) {
			$fetchMode = $this->_adapter->getFetchMode();
		}
		return new Statement($this, $fetchMode);
	}

	/**
	 * Fetches all SQL result rows as an associative array.
	 *
	 * The first column is the key, the entire row array is the
	 * value.  You should construct the query to be sure that
	 * the first column contains unique values, or else
	 * rows with duplicate values in the first column will
	 * overwrite previous data.
	 *
	 * @return \mysqli_stmt
	 */
	public function fetchAssoc()
	{
		return new Statement($this, \MYSQLI_ASSOC);
	}

	/**
	 * Fetches the first column of all SQL result rows as an array.
	 *
	 * @return \mysqli_stmt
	 */
	public function fetchCol()
	{
		return new Statement($this, PDO::FETCH_COLUMN, 0);
	}

	/**
	 * Fetches all SQL result rows as an array of key-value pairs.
	 *
	 * The first column is the key, the second column is the
	 * value.
	 *
	 * @return \mysqli_stmt
	 */
	public function fetchPairs()
	{
		return new Statement($this, PDO::FETCH_KEY_PAIR);
	}
	
	/**
	 * 以回调函数的方式获取
	 * @param string $func
	 * @return \mysqli_stmt
	 */
	public function fetchFunc($func){
		return new Statement($this, PDO::FETCH_FUNC, $func);
	}
	
	/**
	 * 以自定义类的方式获取
	 * @param string $class
	 * @param array $ctor_args
	 * @return \mysqli_stmt
	 */
	public function fetchClass($class, $ctor_args = array()){
		return new Statement($this, PDO::FETCH_CLASS, $class, $ctor_args);
	}
	
	/**
	 * Fetches the first row of the SQL result.
	 * Uses the current fetchMode for the adapter.
	 *
	 * @param mixed $bind Data to bind into SELECT placeholders.
	 * @param mixed				 $fetchMode Override current fetch mode.
	 * @return array
	 */
	public function fetchRow($bind = array(), $fetchMode = null)
	{
		if ($fetchMode === null) {
			$fetchMode = $this->_adapter->getFetchMode();
		}
		$stmt = $this->_adapter->query($this, $bind);
		return $stmt->fetch($fetchMode);
	}
	
	/**
	 * Fetches the first column of the first row of the SQL result.
	 *
	 * @param mixed $bind Data to bind into SELECT placeholders.
	 * @return string
	 */
	public function fetchOne($bind = array())
	{
		$stmt = $this->_adapter->query($this, $bind);
		return $stmt->fetchColumn(0);
	}
	
	/*	fetch方法系列结束，下面是我增加的新方法	*/
	
	/**
	 * 将数据存入一个已经存在的对象
	 * @param mixed $obj
	 * @param array $bind
	 */
	public function fetchInto($obj, $bind = array()){
		$stmt = $this->_adapter->query($this, $bind);
		return $stmt->fetch(PDO::FETCH_INTO, $obj);
	}
	
}
