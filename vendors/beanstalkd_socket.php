<?php
/**
 * Beanstalkd Socket File
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
 * @subpackage queue.vendors
 * @copyright  2009 David Persson <davidpersson@qeweurope.org>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/davidpersson/queue
 */
App::import('Core', 'Socket');
/**
 * Beanstalkd Socket Class
 *
 * Implements the beanstalkd protocol spec 1.2
 *
 * @package    queue
 * @subpackage queue.vendors
 * @link       http://github.com/kr/beanstalkd
 */
class BeanstalkdSocket extends CakeSocket {
/**
 * description
 *
 * @var string
 * @access public
 */
	var $description = 'Beanstalkd DatasSource Interface';
/**
 * Basic Configuration
 *
 * @var array
 * @access protected
 */
	var $_baseConfig = array(
		'persistent' => true,
		'host' => '127.0.0.1',
		'protocol' => 'tcp',
		'port' => 11300,
		'timeout' => 1
	);
/**
 * Writes a packet to the socket
 *
 * @param string $data
 * @access public
 * @return integer|boolean number of written bytes or false on error
 */
	function writePacket($data) {
		return $this->write($data . "\r\n");
	}
/**
 * Reads a packet from the socket
 *
 * @param int $length Number of bytes to read
 * @access public
 * @return string|boolean Data or false on error
 */
	function readPacket($length = null) {
		if (!$this->connected && !$this->connect()) {
			return false;
		}

		if ($length) {
			if (!$data = $this->read($length + 2)) {
				return false;
			}
			$packet = rtrim($data, "\r\n");
		} else {
			$packet = stream_get_line($this->connection, 16384, "\r\n");
		}
		return $packet;
	}

	/* Producer Commands */

/**
 * The "put" command is for any process that wants to insert a job into the queue.
 *
 * @param integer $pri Jobs with smaller priority values will be scheduled
 *                     before jobs with larger priorities.
 *                     The most urgent priority is 0; the least urgent priority is 4294967295.
 * @param integer $delay Seconds to wait before putting the job in the ready queue.
 *                       The job will be in the "delayed" state during this time.
 * @param integer $ttr Time to run - Number of seconds to allow a worker to run this job.
 *                     The minimum ttr is 1.
 * @param string $data The job body
 * @access public
 * @return integer|boolean False on error otherwise and integer indicating the job id
 */
	function put($pri, $delay, $ttr, $data) {
		$this->writePacket(sprintf('put %d %d %d %d', $pri, $delay, $ttr, strlen($data)));
		$this->writePacket($data);
		$status = strtok($this->readPacket(), ' ');

		switch ($status) {
			case 'INSERTED':
			case 'BURIED':
				return (integer)strtok(' '); // job id
			case 'EXPECTED_CRLF':
			case 'JOB_TOO_BIG':
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * The "use" command is for producers. Subsequent put commands will put jobs into
 * the tube specified by this command. If no use command has been issued, jobs
 * will be put into the tube named "default".
 *
 * @param string $tube A name at most 200 bytes. It specifies the tube to use.
 *                     If the tube does not exist, it will be created.
 * @access public
 * @return string|boolean False on error otherwise the tube
 */
	function choose($tube) {
		$this->writePacket(sprintf('use %s', $tube));
		$status = strtok($this->readPacket(), ' ');

		switch ($status) {
			case 'USING':
				return strtok(' ');
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Alias for choose
 */
	function useTube($tube) {
		return $this->choose($tube);
	}

	/* Worker Commands */

/**
 * Reserve a job (with a timeout)
 *
 * @param integer $timeout If given specifies number of seconds to wait for a job. 0 returns immediately.
 * @access public
 * @return array|false False on error otherwise an array holding job id and body
 */
	function reserve($timeout = null) {
		if (isset($timeout)) {
			$this->writePacket(sprintf('reserve-with-timeout %d', $timeout));
		} else {
			$this->writePacket('reserve');
		}
		$status = strtok($this->readPacket(), ' ');

		switch ($status) {
			case 'RESERVED':
				return array(
							'id' => (integer)strtok(' '),
							'body' => $this->readPacket((integer)strtok(' '))
							);
			case 'DEADLINE_SOON':
			case 'TIMED_OUT':
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Removes a job from the server entirely
 *
 * @param integer $id The id of the job
 * @access public
 * @return boolean False on error, true on success
 */
	function delete($id) {
		$this->writePacket(sprintf('delete %d', $id));
		$status = $this->readPacket();

		switch ($status) {
			case 'DELETED':
				return true;
			case 'NOT_FOUND':
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Puts a reserved job back into the ready queue
 *
 * @param integer $id The id of the job
 * @param integer $pri Priority to assign to the job
 * @param integer $delay Number of seconds to wait before putting the job in the ready queue
 * @access public
 * @return boolean False on error, true on success
 */
	function release($id, $pri, $delay) {
		$this->writePacket(sprintf('release %d %d %d', $id, $pri, $delay));
		$status = $this->readPacket();

		switch ($status) {
			case 'RELEASED':
			case 'BURIED':
				return true;
			case 'NOT_FOUND':
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Puts a job into the "buried" state
 *
 * Buried jobs are put into a FIFO linked list and will not be touched
 * until a client kicks them.
 *
 * @param mixed $id
 * @param mixed $pri
 * @access public
 * @return boolean False on error and true on success
 */
	function bury($id, $pri) {
		$this->writePacket(sprintf('bury %d %d', $id, $pri));
		$status = $this->readPacket();

		switch ($status) {
			case 'BURIED':
				return true;
			case 'NOT_FOUND':
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Allows a worker to request more time to work on a job
 *
 * @param integer $id The id of the job
 * @access public
 * @return boolean False on error and true on success
 */
	function touch($id) {
		$this->writePacket(sprintf('touch %d', $id));
		$status = $this->readPacket();

		switch ($status) {
			case 'TOUCJED':
				return true;
			case 'NOT_TOUCHED':
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Adds the named tube to the watch list for the current
 * connection.
 *
 * @param string $tube
 * @access public
 * @return integer|boolean False on error otherwise number of tubes in watch list
 */
	function watch($tube) {
		$this->writePacket(sprintf('watch %s', $tube));
		$status = strtok($this->readPacket(), ' ');

		switch ($status) {
			case 'WATCHING':
				return (integer)strtok(' ');
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Remove the named tube from the watch list
 *
 * @param string $tube
 * @access public
 * @return integer|boolean False on error otherwise number of tubes in watch list
 */
	function ignore($tube) {
		$this->writePacket(sprintf('ignore %s', $tube));
		$status = strtok($this->readPacket(), ' ');

		switch ($status) {
			case 'WATCHING':
				return (integer)strtok(' ');
			case 'NOT_IGNORED':
			default:
				$this->setLastError($status, '');
				return false;
		}
	}

	/* Other Commands */

/**
 * Inspect a job by id
 *
 * @param integer $id The id of the job
 * @access public
 * @return string|boolean False on error otherwise the body of the job
 */
	function peek($id) {
		$this->writePacket(sprintf('peek %d', $id));
		return $this->_peekRead();
	}
/**
 * Inspect the next ready job
 *
 * @access public
 * @return string|boolean False on error otherwise the body of the job
 */
	function peekReady() {
		$this->writePacket('peek-ready');
		return $this->_peekRead();
	}
/**
 * Inspect the job with the shortest delay left
 *
 * @access public
 * @return string|boolean False on error otherwise the body of the job
 */
	function peekDelayed() {
		$this->writePacket('peek-delayed');
		return $this->_peekRead();
	}
/**
 * Inspect the next job in the list of buried jobs
 *
 * @access public
 * @return string|boolean False on error otherwise the body of the job
 */
	function peekBuried() {
		$this->writePacket('peek-buried');
		return $this->_peekRead();
	}
/**
 * Handles response for all peek methods
 *
 * @access public
 * @return string|boolean False on error otherwise the body of the job
 */
	function _peekRead() {
		$status = strtok($this->readPacket(), ' ');

		switch ($status) {
			case 'FOUND':
				return $this->readPacket((integer)strtok(' '));
			case 'NOT_FOUND':
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Moves jobs into the ready queue (applies to the current tube)
 *
 * If there are buried jobs those get kicked only otherwise
 * delayed jobs get kicked.
 *
 * @param integer $bound Upper bound on the number of jobs to kick
 * @access public
 * @return integer|boolean False on error otherwise number of job kicked
 */
	function kick($bound) {
		$this->writePacket(sprintf('kick %d', $bound));
		$status = strtok($this->readPacket(), ' ');

		switch ($status) {
			case 'KICKED':
				return (integer)strtok(' ');
			default:
				$this->setLastError($status, '');
				return false;
		}
	}

	/* Stats Commands */

/**
 * Gives statistical information about the specified job if it exists
 *
 * @param integer $id The job id
 * @access public
 * @return string|boolean False on error otherwise a string with a yaml formatted dictionary
 */
	function statsJob($id) {
	}
/**
 * Gives statistical information about the specified tube if it exists
 *
 * @param string $tube Name of the tube
 * @access public
 * @return string|boolean False on error otherwise a string with a yaml formatted dictionary
 */
	function statsTube($tube) {
	}
/**
 * Gives statistical information about the system as a whole
 *
 * @access public
 * @return string|boolean False on error otherwise a string with a yaml formatted dictionary
 */
	function stats() {
		$this->writePacket('stats');
		$status = strtok($this->readPacket(), ' ');

		switch ($status) {
			case 'OK':
				return $this->readPacket((integer)strtok(' '));
			default:
				$this->setLastError($status, '');
				return false;
		}
	}
/**
 * Returns a list of all existing tubes
 *
 * @access public
 * @return string|boolean False on error otherwise a string with a yaml formatted list
 */
	function listTubes() {
	}
/**
 * Returns the tube currently being used by the producer
 *
 * @access public
 * @return string|boolean False on error otherwise a string with the name of the tube
 */
	function listTubeUsed() {
	}
/**
 * Alias for listTubeUsed
 */
	function listTubeChosen() {
		return $this->listTubeUsed();
	}
/**
 * Returns a list of tubes currently being watched by the worker
 *
 * @access public
 * @return string|boolean False on error otherwise a string with a yaml formatted list
 */
	function listTubesWatched() {
	}
}
?>