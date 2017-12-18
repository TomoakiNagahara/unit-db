<?php
/**
 * unit-db:/DB.class.php
 *
 * @created   2016-11-28
 * @version   1.0
 * @package   unit-db
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */

/** namespace
 *
 * @created   2017-12-18
 */
namespace OP\UNIT\DB;

/** DB
 *
 * @created   2016-11-28
 * @version   1.0
 * @package   unit-db
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
class DB
{
	/** trait
	 *
	 */
	use \OP_CORE;

	/** Save connection configuration.
	 *
	 * @var string
	 */
	private $_config;

	/** Could connection.
	 *
	 * @var boolean
	 */
	private $_connection;

	/** PDO instance handle.
	 *
	 * @var \PDO
	 */
	private $_pdo;

	/** Stack execute queries.
	 *
	 * @var array
	 */
	private $_queries;

	/** sleep
	 *
	 */
	function __sleep()
	{
		return ['_config','_queries'];
	}

	/** wakeup
	 *
	 */
	function __wakeup()
	{
		D("Does not implemented yet.");
	}

	/** Get quote character.
	 *
	 * @return array
	 */
	private function _get_quoter()
	{
		static $lf, $rg;
		if( $lf and $rg ){
			return [$lf, $rg];
		}

		//	...
		switch( $this->_config['driver'] ){
			case 'mysql':
				$lf = $rg = '`';
				break;

			default:
				throw new Exception("This product has not been supported. ({$this->_config['driver']})");
				break;
		}

		//	...
		return [$lf, $rg];
	}

	/** Generate DSN for MySQL.
	 *
	 * @param  $config array
	 * @return $dsn    string
	 */
	private function _GetDsnMySQL($config, &$dsn, &$user, &$password, &$options)
	{
		//	Error check. (错误检查, 錯誤檢查)
		if(!defined('\PDO::MYSQL_ATTR_INIT_COMMAND') ){
			\Notice::Set("Please install MySQL driver for PHP.");
			return false;
		}

		//	Initialize variable. (初始化变量, 初始化變量)
		foreach(['driver','host','user','password','charset'] as $key){
			if( isset($config[$key]) ){
				$this->_config[$key] = ${$key} = $config[$key];
			}else{
				\Notice::Set("Has not been set this key's value. ($key)");
				return false;
			}
		}

		//	Product and server. (产品和服务器, 產品和服務器)
		$dsn = "{$driver}:host={$host}";

		//	Character set. (指定字符代码, 指定字符代碼)
		$options[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '{$charset}'";

		//	Multi statement. (多个指令, 多個指令)
		$options[\PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;

		//	Persistent connect. (持续连接, 持續連接)
		$options[\PDO::ATTR_PERSISTENT] = false;

		//	Select the database. (选择数据库, 選擇數據庫)
		if( isset($config['database']) ){
			$dsn .= ";dbname={$config['database']}";
		}

		//	...
		return true;
	}

	/** Database connection.
	 *
	 * @param  array
	 * @return boolean
	 */
	function Connect($config)
	{
		//	...
		if(!$driver = ifset($config['driver']) ){
			\Notice::Set('Has not been set driver.');
			return;
		}

		//	...
		switch( $driver ){
				case 'mysql':
					if(!self::_GetDsnMySQL($config, $dsn, $user, $password, $options) ){
						return false;
					}
					break;

				default:
					\Notice::Set("This driver has not been supported yet. ($driver)");
					return false;
		}

		//	...
		try{
			$this->_queries[] = $dsn;
			$this->_pdo = new \PDO($dsn, $user, $password, $options);
			$this->_connection = true;
		}catch(Throwable $e){
			$this->_connection = false;
			\Notice::Set($e->getMessage() . " ($dsn, $user)");
		}

		//	...
		return $this->_connection;
	}

	/** Get product name.
	 *
	 * @return string $driver
	 */
	function Driver()
	{
		return $this->_config['driver'];
	}

	/** Get host name.
	 *
	 * @return string $host
	 */
	function Host()
	{
		return $this->_config['host'];
	}

	/** Get port number.
	 *
	 * @return string $port
	 */
	function Port()
	{
		return $this->_config['port'];
	}

	/** Get PDO instance.
	 *
	 * @return \PDO
	 */
	function GetPDO()
	{
		return $this->_pdo;
	}

	/** Get last query.
	 *
	 * @return string
	 */
	function GetQuery()
	{
		return $this->_queries[count($this->_queries)-1];
	}

	/** Get all queries.
	 *
	 * @return array
	 */
	function GetQueries()
	{
		return $this->_queries;
	}

	/** Execute sql query.
	 *
	 * @param  string $query
	 * @return boolean|integer|array
	 */
	function Query($query, $type=null)
	{
		//	...
		if(!$this->_pdo){
			\Notice::Set("Has not been instantiate PDO.", debug_backtrace());
			return false;
		}

		//	...
		$query = trim($query);

		//	...
		$this->_queries[] = $query;
		if(!$statement = $this->_pdo->query($query)){
			$errorinfo = $this->_pdo->errorInfo();
			$state = $errorinfo[0];
			$errno = $errorinfo[1];
			$error = $errorinfo[2];
			\Notice::Set("[$state($errno)] $error", debug_backtrace());
			return false;
		}

		//	...
		if(!$type){
			$type = substr($query, 0, strpos($query, ' '));
		}

		//	...
		switch( strtolower($type) ){
			case 'show':
				//	...
				$column = strpos($query, 'SHOW FULL COLUMNS FROM') === 0 ? true: false;
				$index  = strpos($query, 'SHOW INDEX FROM')        === 0 ? true: false;

				//	...
				foreach( $statement->fetchAll(\PDO::FETCH_ASSOC) as $temp ){
					if( $column ){
						$name = $temp['Field'];
						foreach( $temp as $key => $val ){
							//	...
							$key = lcfirst($key);

							//	...
							if( $st = strpos($val, '(') and $en = strpos($val, ')') ){
								$type   = substr($val, 0, $st);
								$length = substr($val, $st+1, $en - $st -1 );
								$length = (int)$length;
								$result[$name]['type']   = $type;
								$result[$name]['length'] = $length;
								continue;
							}

							//	...
							if( $key === 'null' ){
								$val = $val === 'YES' ? true: false;
							}

							//	...
							if( $key === 'key' ){
								$val = strtolower($val);
							}

							//	...
							$result[$name][$key] = $val;
						}
					}else if( $index ){
						$name = $temp['Key_name'];
						$seq  = $temp['Seq_in_index'];
						$result[$name][$seq] = $temp;
					}else{
						foreach( $temp as $key => $val ){
							$result[] = $val;
						}
					}
				}
				break;

			case 'select':
				$result = $statement->fetchAll(\PDO::FETCH_ASSOC);
				if( strpos($query.' ', ' LIMIT 1 ') and $result ){
					$result = $result[0];
				}
				break;

			case 'count':
				$result = $statement->fetchAll(\PDO::FETCH_ASSOC);
				$result = $result[0]['COUNT(*)'];
				break;

			case 'insert':
				$result = $this->_pdo->lastInsertId(/* $name is necessary at PGSQL */);
				break;

			case 'update':
			case 'delete':
				$result = $statement->rowCount();
				break;

			case 'alter':
			case 'grant':
			case 'create':
				$result = true;
				break;

			case 'set':
				$result = true;
				break;

			default:
				d($type);
		}

		//	...
		return isset($result) ? $result: [];
	}

	/** Quick Query Language.
	 *
	 * <pre>
	 * //	Space is required.
	 *
	 * //	Basic SELECT
	 * $value = 1;
	 * $this->Quick("TABLE.column = $value"); // Equal
	 * $this->Quick("TABLE.column > $value"); // Grater than
	 * $this->Quick("TABLE.column > " . $value - 1); // Grater than equal
	 * $this->Quick("TABLE.column != $value"); // Not equal
	 *
	 * //	Get single column
	 * $this->Quick("score <- TABLE.date < $today");
	 *
	 * //	Limit
	 * $this->Quick("score <- TABLE.date < $today", "limit=1");
	 *
	 * //	Order (default is ASC)
	 * $this->Quick("score <- TABLE.date < $today", "limit=1, order=id timestamp");
	 *
	 * //	Order (DESC)
	 * $this->Quick("score <- TABLE.date < $today", "limit=1, order=^asc desc^");
	 *
	 * //	Function
	 * $this->Quick("sum(score) <- TABLE.date < $today");
	 * </pre>
	 *
	 * @param  string $qql
	 * @return array
	 */
	function Quick($qql, $option=[])
	{
		//	...
		if(!class_exists('QQL', false)){
			if(!include(__DIR__.'/QQL.class.php')){
				return [];
			}
		}

		//	...
		return QQL::Select($qql, $option, $this);
	}

	/** Quote key string.
	 *
	 * @param  string $val
	 * @return string
	 */
	function Quote($str)
	{
		list($l, $r) = $this->_get_quoter();
		$str = str_replace([$l, $r], '', $str);
		return $l.trim($str).$r;
	}
}
