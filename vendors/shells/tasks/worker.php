<?php
/**
 * Make Task File
 *
 * Copyright (c) 2007-2009 David Persson
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * PHP version 5
 * CakePHP version 1.2
 *
 * @package    media
 * @subpackage media.shells.tasks
 * @copyright  2007-2009 David Persson <davidpersson@qeweurope.org>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/davidpersson/media
 */
Configure::write('debug', 2);
/**
 * Make Task Class
 *
 * @package    media
 * @subpackage media.shells.tasks
 */
class WorkerTask extends ManageShell {
	var $uses = array('Queue.Job');
	var $tubes = array('default');

	function execute() {
		$this->heading('Debug Worker');
		$this->tubes = explode(',', $this->in('Tubes to watch (separate with comma)', null, 'default'));

		while (true) {
			$this->hr(':');
			$this->out('Waiting for a job... STRG+C to abort');
			$job = $this->Job->reserve(array('tubes' => $this->tubes));
			$this->out();
			$this->out('Got:');
			$this->out(var_export($job, true));
			$this->out();
			$this->out('[D]elete');
			$this->out('[B]ury');
			$this->out('[R]elease');
			$this->out('[T]ouch');
			$action = $this->in('What would you like to do?', 'D,B,R,T', 'D');

			switch(up($action)) {
				case 'D':
					$result = $this->Job->delete();
					break;
				case 'B':
					$result = $this->Job->bury();
					break;
				case 'R':
					$result = $this->Job->release();
					break;
				case 'T':
					$result = $this->Job->touch();
					break;
			}
			$this->out($result ? 'OK' : 'FAILED');

			if (low($this->in('Continue?', array('y', 'n'), 'y')) == 'n') {
				$this->_stop();
			}
		}
	}
}
?>
