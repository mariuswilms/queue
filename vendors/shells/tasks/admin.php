<?php
/**
 * Admin Task File
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

/**
 * Admin Task Class
 *
 * @package    queue
 * @subpackage queue.shells.tasks
 */
class AdminTask extends QueueShell {

	var $uses = array('Queue.Job');

	var $tubes = array('default');

	function execute() {
		$this->verbose = isset($this->params['verbose']);

		$this->out('[K]ick a certain number of jobs back into the ready queue.');
		$action = $this->in('What would you like to do?', 'K');

		switch(up($action)) {
			case 'K':
				$result = $this->Job->kick(array('bound' => $this->in('Number of jobs:', null, 100)));;
				break;
		}
		$this->out($result ? 'OK' : 'FAILED');
	}

}
