<?php
/**
 * Producer Task File
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
 * @subpackage queue.shells.tasks
 * @copyright  2009 David Persson <davidpersson@gmx.de>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/davidpersson/queue
 */
Configure::write('debug', 2);
/**
 * Producer Task Class
 *
 * @package    queue
 * @subpackage queue.shells.tasks
 */
class ProducerTask extends QueueShell {
	var $uses = array('Queue.Job');
	var $count = 0;
	var $tube = 'default';

	function execute() {
		$this->out('Debug Producer');
		$this->hr();
		$this->tube = $this->in('Tube to use', null, 'default');

		while (true) {
			$this->count++;
			$body = $this->in('Data to put:', null, 'This is test #' . $this->count);
			$result = $this->Job->put(compact('body'));
			$this->out($result ? 'OK Job ID ' . $this->Job->id : 'FAILED');

			if (low($this->in('Continue?', array('y', 'n'), 'y')) == 'n') {
				$this->_stop();
			}
		}
	}
}
?>