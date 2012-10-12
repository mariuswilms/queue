<?php
/**
 * Job Model File
 *
 * Copyright (c) 2009-2012 David Persson
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * PHP version 5
 * CakePHP version 1.2
 *
 * @package    queue
 * @subpackage queue.models
 * @copyright  2009-2012 David Persson <davidpersson@gmx.de>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/davidpersson/queue
 */

App::import('Core', 'ConnectionManager');

/**
 * Job Model Class
 *
 * @package    queue
 * @subpackage queue.models
 */
class Job extends QueueAppModel {

/**
 * Database configuration to use. Before using this model be sure to
 * define at least a minimal connection in your database.php file:
 * {{{
 *   // ...
 *	'queue' => array('datasource' => 'beanstalkd')
 * }}}
 *
 * @var string
 */
	var $useDbConfig = 'queue';

/**
 * Check to see if queue is online and accepts jobs.
 *
 * @param array $cached Pass `true` to enable cached responses.
 * @return boolean
 */
	function online($cached = false) {
		static $status;

		if ($cached && isset($status)) {
			return $status;
		}
		$Manager = ConnectionManager::getInstance();
		@$DataSource = $Manager->getDataSource($this->useDbConfig);

		return $status = $DataSource && $DataSource->isConnected();
	}
}

?>