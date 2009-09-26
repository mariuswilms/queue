<?php
/**
 * Beanstalkd Source File
 *
 * Copyright (c) 2009 David Persson
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * PHP version 5
 * CakePHP version 1.2
 *
 * @package    queue
 * @subpackage queue.models.datasources
 * @copyright  2009 David Persson <davidpersson@gmx.de>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/davidpersson/queue
 */
App::import('Core', 'DataSource');
App::import('Vendor', 'Queue.BeanstalkdSocket');
/**
 * Beanstalkd Source Class
 *
 * @package    queue
 * @subpackage queue.models.datasources
 */
class BeanstalkdSource extends DataSource {
/**
 * Holds ID of last inserted job
 *
 * @var mixed
 * @access private
 */
	var $__insertID = null;
/**
 * The default configuration of a specific DataSource
 *
 * @var array
 * @access public
 */
	var $_baseConfig = array(
		'host' => '127.0.0.1',
		'port' => 11300,
		'ttr' => 120,
		'format' => 'php'
	);

	function __construct($config = array()) {
		parent::__construct();
		$this->setConfig($config);
		$this->fullDebug = Configure::read('debug') > 1;
		$this->connection = new BeanstalkdSocket($this->config);
		$this->connected =& $this->connection->connected;
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
		if (!$this->connection->connect()) {
			$error = $this->lastError();
			trigger_error("BeanstalkdSource - Could not connect. Error given was '{$error}'.", E_USER_WARNING);
			return false;
		}
		return true;
	}

	function disconnect() {
		return $this->connection->disconnect();
	}

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

		$id = $this->connection->put($priority, $delay, $ttr, $this->_encode($body));

		if ($id !== false) {
			$Model->setInsertId($id);
			return $this->__insertID = $Model->id = $id;
		}
		return false;
	}

	function choose(&$Model, $tube) {
		return $this->connection->choose($tube) === $tube;
	}

	function reserve(&$Model, $options = array()) {
		$default = array(
						'timeout' => null,
						'tube' => null,
						);
		extract(array_merge($default, $options));

		if ($tube && !$this->watch($Model, $tube)) {
			return false;
		}

		if (!$result = $this->connection->reserve($timeout)) {
			return false;
		}
		$result['body'] = $this->_decode($result['body']);
		return $Model->set(array($Model->alias => $result));
	}

	function watch(&$Model, $tube) {
		foreach ((array)$tube as $t) {
			if (!$this->connection->watch($t)) {
				return false;
			}
		}
		return true;
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
		return $this->connection->release($id, $priority, $delay);
	}

	function statistics(&$Model) {
		return $this->connection->stats();
	}

	function _encode($data) {
		switch ($this->config['format']) {
			case 'json':
				return json_encode($data);
			case 'php':
			default:
				return serialize($data);
		}
	}

	function _decode($data) {
		switch ($this->config['format']) {
			case 'json':
				return json_decode($data);
			case 'php':
			default:
				return unserialize($data);
		}
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
				trigger_error("BeanstalkdSource::query - Unkown method {$method}.", E_USER_WARNING);
				return false;
		}
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
 * Finds a job
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
 * Deletes a job
 *
 * @param Model $Model
 * @param mixed $id
 */
	function delete(&$Model, $id = null) {
		if ($id == null) {
			$id = $Model->id;
		}
		return $this->connection->delete($id);
	}
/**
 * Caches/returns cached results for child instances
 *
 * @return array
 */
	function listSources($data = null) {
		return array();
	}
/**
 * Returns a Model description (metadata) or null if none found.
 *
 * @param Model $model
 * @return mixed
 */
	function describe($model) {
		return null;
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

	function logQuery($method, $params) {
		$this->_queriesCnt++;
		$this->_queriesTime += $this->took;
		$this->_queriesLog[] = array(
			'query' => $method,
			'error' => $this->error,
			'took' => $this->took,
			'affected' => 0,
			'numRows' => 0
		);
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

	function lastError() {
		return $this->connection->lastError();
	}
/**
 * Returns the ID generated from the previous INSERT operation.
 *
 * @param unknown_type $source
 * @return in
 */
	function lastInsertId($source = null) {
		return $this->__insertID;
	}
}
?>