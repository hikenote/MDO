<?php
namespace MDO;

class Statement implements \IteratorAggregate, \Countable
{
	const FETCH_DATAOBJECT = 'fetchDataObject';
	const FETCH_CLASSFUNC = 'fetchClassFunc';
	
	/**
	 * 尚未发射的语句
	 * @var array
	 */
	protected static $_waitingQueue = array();
	
	/**
	 * 已经发射的语句
	 * @var array
	 */
	protected static $_fetchingQueue = array();
	
	/**
	 * 
	 * @var \mysqli_stmt
	 */
	protected static $_stmt = null;
	
	/**
	 * 
	 * @var Select
	 */
	protected $_select;
	
	protected $_fetchArgument;
	
	protected $_ctorArgs;
	
	protected $_rowset = false;
	
	/**
	 * 将buffer中现有的所有结果集都取回来
	 */
	public static function flush(){
		if (self::$_stmt === null)
			return;
		
		while(self::$_stmt->nextRowset()){
			$query = array_shift(self::$_fetchingQueue);
			$query->_rowset = $query->_fetchAll();
		}
		self::$_stmt = null;
	}
	
	/**
	 * 构造函数
	 * 
	 * @param $select
	 * @param $fetchMode
	 * @param $fetchArgument
	 * @param $ctorArgs
	 */
	public function __construct($select, $fetchMode = null, $fetchArgument = null, $ctorArgs = null){
		$this->_select = $select;
		$this->_fetchMode = $fetchMode;
		$this->_fetchArgument = $fetchArgument;
		$this->_ctorArgs = $ctorArgs;
		
		self::$_waitingQueue[] = $this;
	}
	
	public function __toString(){
		return $this->_select->assemble();
	}
	
	public function __call($name, $args){
		$this->_query();
		 
		return call_user_func_array(array($this->_rowset, $name), $args);
	}
	
	protected function _fetchAll(){
		switch ($this->_fetchMode){
			case self::FETCH_DATAOBJECT:
				self::$_stmt->setFetchMode(\MYSQLI_ASSOC);
				$rowset = new \SplFixedArray(self::$_stmt->rowCount());
				
				$rowClass = $this->_fetchArgument;
				foreach (self::$_stmt as $index => $data)
					$rowset[$index] = new $rowClass($data, true, $this->_ctorArgs);
				
				return $rowset;
			
			case self::FETCH_CLASSFUNC:
				self::$_stmt->setFetchMode(\MYSQLI_ASSOC);
				$rowset = new \SplFixedArray(self::$_stmt->rowCount());
				
				$classFunc = $this->_fetchArgument;
				foreach (self::$_stmt as $index => $data){
					$rowClass = $classFunc($data);
					$rowset[$index] = new $rowClass($data, true, $this->_ctorArgs);
				}
				return $rowset;
				
			default:
				if (isset($this->_ctorArgs))
					return self::$_stmt->fetchAll($this->_fetchMode, $this->_fetchArgument, $this->_ctorArgs);
				
				if (isset($this->_fetchArgument))
					return self::$_stmt->fetchAll($this->_fetchMode, $this->_fetchArgument);
					
				if (isset($this->_fetchMode))
					return self::$_stmt->fetchAll($this->_fetchMode);
		}
	}
	
	/**
	 * 
	 * @throws \mysqli_sql_exception
	 */
	public function _query(){
		if ($this->_rowset === false){
			
			if (self::$_stmt){
				while(self::$_stmt->nextRowset()){//如果已经在结果缓存中，则搜寻结果集
					$query = array_shift(self::$_fetchingQueue);
					$query->_rowset = $query->_fetchAll();
					
					if ($query === $this)
						return;
				}
				self::$_stmt = null;
			}
			
			//将当前的语句插到第一个，然后把所有语句一口气打包发送给mysql
			$keys = array_keys(self::$_waitingQueue, $this);
			
			if (count($keys))
				unset(self::$_waitingQueue[$keys[0]]);
			
			
			$sql = $this->_select->assemble();
			if (count(self::$_waitingQueue))
				$sql .= ";\n" . implode(";\n", self::$_waitingQueue);
			
			implode(";\n", self::$_waitingQueue);
			
			self::$_stmt = $this->_select->getAdapter()->query($sql);
			
			$this->_rowset = $this->_fetchAll();
			
			self::$_fetchingQueue = self::$_waitingQueue;
			
			self::$_waitingQueue = array();
		}
	}
	
	/**
	 * 强制获得结果集
	 * 
	 * @return mixed
	 */
	public function fetch(){
		$this->_query();
		
		return $this->_rowset;
	}
	
	/**
	 * 获得迭代器，支持foreach
	 */
	public function getIterator(){
		$this->_query();
		
		if (is_array($this->_rowset))
			return new \ArrayIterator($this->_rowset);
		
		return $this->_rowset;
	}
	
	public function current(){
		$this->_query();
		
		if ($this->_rowset instanceof \SplFixedArray){
			// php 5.4.6中有一个bug, 在小概率(5%)情况下，SplFixedArray的count不为0，但是current()取不到实际的数据，var_dump()时也显示SplFixedArray(0)，必须用[0]才能取到数据。
			return $this->_rowset->count()
				? ($this->_rowset->current() ?: $this->_rowset[0])
				: null;
		}
		else{//普通数组或者其他实现了count和current方法的对象
			return count($this->_rowset) ? current($this->_rowset) : null;
		}
	}
	
	public function count() {
		$this->_query();
		
		return count($this->_rowset);
	}
	
	/**
	 * 用来代替current
	 * 
	 * @return mixed
	 */
	public function first(){
		$this->_query();
		 
		return isset($this->_rowset[0]) ? $this->_rowset[0] : null;
	}
}
