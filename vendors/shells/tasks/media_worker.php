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
App::import('Lib', 'Media.Media');

/**
 * Media Worker Task Class
 *
 * @package    queue
 * @subpackage queue.shells.tasks
 */
class MediaWorkerTask extends QueueShell {

	var $description = 'Media Worker';

	var $uses = array('Queue.Job');

	var $tubes = array('default');

	var $verbose = false;

	function execute() {
		$this->verbose = isset($this->params['verbose']);

		if (isset($this->params['description'])) {
			$this->description = $this->params['description'];
		}

		$this->log('debug', 'Starting up.');
		$this->out($this->description);
		$this->hr();

		if ($this->args) {
			$tubes = array_shift($this->args);
			$this->interactive = false;
		}
		$this->tubes = explode(',', $this->in('Tubes to watch (separate with comma)', null, 'default'));

		while (true) {
			$this->hr();
			$message = 'Watching tubes ' . implode(', ', $this->tubes) . '.';
			$this->log('debug', $message);
			$this->out($message);

			$message = 'Waiting for job...';
			$this->log('debug', $message);
			$this->out("{$message} STRG+C to abort.");

			$job = $this->Job->reserve(array('tubes' => $this->tubes));
			$this->out('');

			if (!$job) {
				$message = 'Got invalid job.';
				$this->log('error', $message);
				$this->error($message);
				continue;
			}
			$message = "Got job `{$this->Job->id}`.";
			$this->log('debug', $message);
			$this->out($message);

			if ($this->verbose) {
				$this->out(var_export($job, true));
				$this->out('');
			}

			$message = "Deriving media for job `{$this->Job->id}`...";
			$this->log('debug', $message);
			$this->out($message);

			extract($job['Job'], EXTR_OVERWRITE);
			extract($process, EXTR_OVERWRITE);
			$result = false;

			if (!$Media = Media::make($file, $instructions)) {
				$message  = "Failed to make version `{$version}` ";
				$message .= "of file `{$file}` part of job `{$this->Job->id}`. ";
				$this->log('error', $message);
				$this->err($message);
			} else {
				$result = $Media->store($directory . basename($file), $overwrite);
			}
			if ($result) {
				$this->Job->delete();

				$message = "Job `{$this->Job->id}` deleted.";
				$this->log('debug', $message);
				$this->out("OK. {$message}");
			} else {
				$this->Job->bury();

				$message = "Job `{$this->Job->id}` buried.";
				$this->log('debug', $message);
				$this->out("FAILED. {$message}");
			}
		}
		$this->log('debug', 'Exiting.');
	}

	function log($type, $message) {
		$message = "{$this->description} - {$message}";
		return $this->log($type, $message);
	}

}

?>