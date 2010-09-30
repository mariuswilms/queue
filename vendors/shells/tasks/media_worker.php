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

/**
 * Media Worker Task Class
 *
 * @package    queue
 * @subpackage queue.shells.tasks
 */
class MediaWorkerTask extends QueueShell {

	var $description = 'Media Worker';

	var $uses = array('Queue.Job');

	var $tubes;

	var $verbose = false;

	var $model;

	function execute() {
		$this->verbose = isset($this->params['verbose']);

		if (isset($this->params['description'])) {
			$this->description = $this->params['description'];
		}
		if (isset($this->params['model'])) {
			$this->model = $this->params['model'];
		} else {
			$this->model = $this->in('Model', null, 'Media.Attachment');
		}
		$this->_Model = ClassRegistry::init($this->model);

		if (!isset($this->_Model->Behaviors->Generator)) {
			$this->error("Model `{$this->model}` has the `Generator` behavior not attached to it.");
		}

		if (isset($this->params['tube'])) {
			$this->tubes = array($this->params['tube']);
		} elseif (isset($this->params['tubes'])) {
			$this->tubes = explode(',', $this->params['tubes']);
		} else {
			$this->tubes = explode(',', $this->in('Tubes to watch (separate with comma)', null, 'default'));
		}

		$this->log('Starting up.', 'debug');
		$this->out($this->description);
		$this->hr();

		$message = 'Watching tubes ' . implode(', ', $this->tubes) . '.';
		$this->log($message, 'debug');
		$this->out($message);

		while (true) {
			$this->hr();

			$message = 'Waiting for job...';
			$this->log($message, 'debug');
			$this->out("{$message} STRG+C to abort.");

			$job = $this->Job->reserve(array('tube' => $this->tubes));
			$this->out();

			if (!$job) {
				$message = 'Got invalid job.';
				$this->log($message, 'error');
				$this->error($message);
				continue;
			}
			$message = "Got job `{$this->Job->id}`.";
			$this->log($message, 'debug');
			$this->out($message);

			if ($this->verbose) {
				$this->out(var_export($job, true));
				$this->out();
			}

			$message = "Deriving media for job `{$this->Job->id}`...";
			$this->log($message, 'debug');
			$this->out($message);

			extract($job['Job'], EXTR_OVERWRITE);
			$result = false;

			if ($this->_Model->makeVersion($file, $process + array('delegate' => false))) {
				$message = "Job `{$this->Job->id}` deleted."; // id is unset after delete

				$this->Job->delete();

				$this->log($message, 'debug');
				$this->out("OK. {$message}");

			} else {
				$message  = "Failed to make version `{$process['version']}` ";
				$message .= "of file `{$file}` part of job `{$this->Job->id}`. ";
				$this->log($message, 'error');
				$this->err($message);

				$this->Job->bury();

				$message = "Job `{$this->Job->id}` buried.";
				$this->log($message, 'debug');
				$this->out("FAILED. {$message}");
			}
		}
		$this->log('Exiting.', 'debug');
	}

	function log($message, $type = 'debug') {
		$message = "{$this->description} - {$message}";
		return parent::log($message, $type);
	}
}

?>