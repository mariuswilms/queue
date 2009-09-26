<?php
/**
 * Media Worker Task File
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
App::import('Vendor', 'Media.Media');
/**
 * Media Worker Task Class
 *
 * @package    queue
 * @subpackage queue.shells.tasks
 */
class MediaWorkerTask extends QueueShell {
	var $uses = array('Queue.Job');
	var $tubes = array('default');

	function execute() {
		$this->out('Media Worker');
		$this->hr();

		while (true) {
			$this->hr();
			$this->out('Waiting for a job... STRG+C to abort.');
			$job = $this->Job->reserve(array('tubes' => $this->tubes));
			$this->out('');

			$this->out("Deriving media for job {$this->Job->id}.");
			$this->out(var_export($job, true));
			$this->out('');

			$result = Media::make($file, $instructions);

			if ($result) {
				$this->Job->delete();
				$this->out("OK. Job {$this->Job->id} deleted.");
			} else {
				$this->Job->bury();
				$this->err("FAILED. Job {$this->Job->id} buried.");
			}
		}
	}
}