<?php
App::import('Core', 'DataSource');
App::import('Vendor', 'Queue.BeanstalkSocket');
/**
 * BeanstalkSource Class
 *
 * Long description for file
 *
 * @package       queue
 * @subpackage    queue.model.datasources
 */
class BeanstalkSource extends DataSource {
/**
 * Are we connected to the DataSource?
 *
 * @var boolean
 * @access public
 */
	var $connected = false;
/**
 * Print full query debug info?
 *
 * @var boolean
 * @access public
 */
	var $fullDebug = false;
/**
 * Error description of last query
 *
 * @var unknown_type
 * @access public
 */
	var $error = null;
/**
 * String to hold how many rows were affected by the last SQL operation.
 *
 * @var string
 * @access public
 */
	var $affected = null;
/**
 * Number of rows in current resultset
 *
 * @var int
 * @access public
 */
	var $numRows = null;
/**
 * Time the last query took
 *
 * @var int
 * @access public
 */
	var $took = null;
/**
 * The starting character that this DataSource uses for quoted identifiers.
 *
 * @var string
 */
	var $startQuote = null;
/**
 * The ending character that this DataSource uses for quoted identifiers.
 *
 * @var string
 */
	var $endQuote = null;
/**
 * Enter description here...
 *
 * @var array
 * @access private
 */
	var $_result = null;
/**
 * Queries count.
 *
 * @var int
 * @access private
 */
	var $_queriesCnt = 0;
/**
 * Total duration of all queries.
 *
 * @var unknown_type
 * @access private
 */
	var $_queriesTime = null;
/**
 * Log of queries executed by this DataSource
 *
 * @var unknown_type
 * @access private
 */
	var $_queriesLog = array();
/**
 * Maximum number of items in query log, to prevent query log taking over
 * too much memory on large amounts of queries -- I we've had problems at
 * >6000 queries on one system.
 *
 * @var int Maximum number of queries in the queries log.
 * @access private
 */
	var $_queriesLogMax = 200;
/**
 * Caches serialzed results of executed queries
 *
 * @var array Maximum number of queries in the queries log.
 * @access private
 */
	var $_queryCache = array();
/**
 * The default configuration of a specific DataSource
 *
 * @var array
 * @access public
 */
	var $_baseConfig = array(
					'host' => '127.0.0.1',
					'port' => '11300',
					'ttr' => 120,
					);
/**
 * Holds references to descriptions loaded by the DataSource
 *
 * @var array
 * @access private
 */
	var $__descriptions = array();
/**
 * Holds a list of sources (tables) contained in the DataSource
 *
 * @var array
 * @access protected
 */
	var $_sources = null;
/**
 * A reference to the physical connection of this DataSource
 *
 * @var array
 * @access public
 */
	var $connection = null;
/**
 * The DataSource configuration
 *
 * @var array
 * @access public
 */
	var $config = array();
/**
 * The DataSource configuration key name
 *
 * @var string
 * @access public
 */
	var $configKeyName = null;
/**
 * Whether or not this DataSource is in the middle of a transaction
 *
 * @var boolean
 * @access protected
 */
	var $_transactionStarted = false;
/**
 * Whether or not source data like available tables and schema descriptions
 * should be cached
 *
 * @var boolean
 */
	var $cacheSources = true;

	var $Socket;

	function __construct($config = array()) {
		parent::__construct();
		$this->setConfig($config);
		$this->fullDebug = Configure::read('debug') > 1;
		$this->Socket = new BeanstalkSocket($this->config);
		$this->connected =& $this->Socket->connected;
		$this->connect();
	}

	function close() {
		if ($this->connected) {
			$this->disconnect();
		}
		if ($this->fullDebug) {
			$this->showLog();
		}
	}


	function connect() {
		if (!$this->Socket->connect()) {
			$error = $this->lastError();
			trigger_error("BeanstalkSource - Could not connect. Error given was '{$error}'.", E_USER_WARNING);
			return false;
		}
		return true;
	}

	function disconnect() {
		return $this->Socket->disconnect();
	}


	function query($method, $params, &$Model) {
		array_unshift($params, $Model);

		$startQuery = microtime(true);

		switch ($method) {
			case 'release':
			case 'delete':
			case 'touch':
			case 'bury':
			case 'put':
			case 'reserve':
			case 'statistics':
				$result = $this->dispatchMethod($method, $params);
				$this->took = microtime(true) - $startQuery;
				$this->error = $this->lastError();
				$this->logQuery($method, $params);
				return $result;
			default:
				trigger_error("BeanstalkSource::query - Unkown method {$method}.", E_USER_WARNING);
				return false;
		}
	}

	function lastError() {
		return $this->Socket->lastError();
	}

	function showLog() {
		$text = __n('query', 'queries', $this->_queriesCnt, true);

		if (PHP_SAPI !== 'cli') {
			printf('<table class="cake-sql-log" id="cakeSqlLog_%s" summary="Cake SQL Log">',
							uniqid());
			printf('<caption>(%s) %d %s took %.4f ms</caption>',
							$this->configKeyName, $this->_queriesCnt, $text, $this->_queriesTime);
			printf("<thead>\n<tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr>\n</thead>\n<tbody>\n",
							'Nr', 'Query', 'Error', 'Affected', 'Num. rows', 'Took (ms)');

			foreach ($this->_queriesLog as $key => $value) {
				printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%d</td><td>%.4f</td><td>%d</td></tr>',
								$key + 1, $value['query'], $value['error'],
								$value['affected'],	$value['numRows'], $value['took']);
			}
		} else {
			printf('(%s) %d %s took %.4f ms' . "\n",
							$this->configKeyName, $this->_queriesCnt, $text, $this->_queriesTime);

			foreach ($this->_queriesLog as $key => $value) {
				printf('%d %s %s %.4f ms' . "\n",
								$key + 1, $value['query'], $value['error'], $value['took']);
			}
		}
	}

	function logQuery($method, $params) {
		$this->_queriesCnt++;
		$this->_queriesTime += $this->took;
		$this->_queriesLog[] = array(
						'query' => $method,
						'error' => $this->error,
						'took' => $this->took,
						'affected' => 0,
						'numRows' => 0,
						);
	}

	function _encode($data) {
		return serialize($data);
	}

	function _decode($data) {
		return unserialize($data);
	}

	/* Producer */

	function put(&$Model, $data) {
		$body = null;
		$priority = 0;
		$delay = 0;
		$ttr = $this->config['ttr'];
		$tube = 'default';

		$Model->set($data);
		extract($Model->data[$Model->alias], EXTR_OVERWRITE);

		if (!$this->choose($Model, $tube)) {
			return false;
		}

		$id = $this->Socket->put($priority, $delay, $ttr, $this->_encode($body));

		if ($id !== false) {
			$Model->setInsertId($id);
			return $Model->id = $id;
		}
		return false;
	}

	function choose(&$Model, $tube) {
		return $this->Socket->choose($tube) === $tube;
	}

	/* Worker */

	function reserve(&$Model, $options = array()) {
		$default = array(
						'timeout' => null,
						'tubes' => null,
						);
		extract(array_merge($default, $options));

		if ($tubes) {
			foreach ((array)$tubes as $tube) {
				if (!$this->Socket->watch($tube)) {
					return false;
				}
			}
		}

		if (!$result = $this->Socket->reserve($timeout)) {
			return false;
		}
		$result['body'] = $this->_decode($result['body']);
		return $Model->set(array($Model->alias => $result));
	}

	function release(&$Model, $options = array()) {
		if (!is_array($options)) {
			$options = array('id' => $options);
		}
		$id = null;
		$priority = 0;
		$delay = 0;

		extract($options, EXTR_OVERWRITE);

		if ($id === null) {
			$id = $Model->id;
		}
		return $this->Socket->release($id, $priority, $delay);
	}


	/* Other commands */

	function statistics(&$Model) {
		return $this->Socket->stats();
	}


/**
 * Caches/returns cached results for child instances
 *
 * @return array
 */
	function listSources($data = null) {
		if ($this->cacheSources === false) {
			return null;
		}

		if ($this->_sources !== null) {
			return $this->_sources;
		}

		$key = ConnectionManager::getSourceName($this) . '_' . $this->config['database'] . '_list';
		$key = preg_replace('/[^A-Za-z0-9_\-.+]/', '_', $key);
		$sources = Cache::read($key, '_cake_model_');

		if (empty($sources)) {
			$sources = $data;
			Cache::write($key, $data, '_cake_model_');
		}

		$this->_sources = $sources;
		return $sources;
	}
/**
 * Convenience method for DboSource::listSources().  Returns source names in lowercase.
 *
 * @return array
 */
	function sources($reset = false) {
		if ($reset === true) {
			$this->_sources = null;
		}
		return array_map('strtolower', $this->listSources());
	}
/**
 * Returns a Model description (metadata) or null if none found.
 *
 * @param Model $model
 * @return mixed
 */
	function describe($model) {
		if ($this->cacheSources === false) {
			return null;
		}
		if (isset($this->__descriptions[$model->tablePrefix . $model->table])) {
			return $this->__descriptions[$model->tablePrefix . $model->table];
		}
		$cache = $this->__cacheDescription($model->tablePrefix . $model->table);

		if ($cache !== null) {
			$this->__descriptions[$model->tablePrefix . $model->table] =& $cache;
			return $cache;
		}
		return null;
	}
/**
 * To-be-overridden in subclasses.
 *
 * @param unknown_type $model
 * @param unknown_type $fields
 * @param unknown_type $values
 * @return unknown
 */
	function create(&$model, $fields = null, $values = null) {
		return false;
	}
/**
 * To-be-overridden in subclasses.
 *
 * @param unknown_type $model
 * @param unknown_type $queryData
 * @return unknown
 */
	function read(&$model, $queryData = array()) {
		return false;
	}
/**
 * To-be-overridden in subclasses.
 *
 * @param unknown_type $model
 * @param unknown_type $fields
 * @param unknown_type $values
 * @return unknown
 */
	function update(&$model, $fields = null, $values = null) {
		return false;
	}
/**
 * To-be-overridden in subclasses.
 *
 * @param unknown_type $model
 * @param unknown_type $id
 */
	function delete(&$model, $id = null) {
		if ($id == null) {
			$id = $model->id;
		}
	}
/**
 * Returns the ID generated from the previous INSERT operation.
 *
 * @param unknown_type $source
 * @return in
 */
	function lastInsertId($source = null) {
		return false;
	}
/**
 * Returns the ID generated from the previous INSERT operation.
 *
 * @param unknown_type $source
 * @return in
 */
	function lastNumRows($source = null) {
		return false;
	}
/**
 * Returns the ID generated from the previous INSERT operation.
 *
 * @param unknown_type $source
 * @return in
 */
	function lastAffected($source = null) {
		return false;
	}
/**
 * Returns true if the DataSource supports the given interface (method)
 *
 * @param string $interface The name of the interface (method)
 * @return boolean True on success
 */
	function isInterfaceSupported($interface) {
		$methods = get_class_methods(get_class($this));
		$methods = strtolower(implode('|', $methods));
		$methods = explode('|', $methods);
		$return = in_array(strtolower($interface), $methods);
		return $return;
	}
/**
 * To-be-overridden in subclasses.
 *
 * @param unknown_type $model
 * @param unknown_type $key
 * @return unknown
 */
	function resolveKey($model, $key) {
		return $model->alias . $key;
	}
}
?>
