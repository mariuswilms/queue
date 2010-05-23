<?php
/**
 * Queue Shell File
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
 * @subpackage queue.shells
 * @copyright  2009 David Persson <davidpersson@gmx.de>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/davidpersson/queue
 */
Configure::write('Cache.disable', true);

/**
 * Manage Shell Class
 *
 * @package    queue
 * @subpackage queue.shells
 */
class QueueShell extends Shell {

/**
 * Tasks to load. Additional tasks are also loaded dynamically.
 *
 * @see main()
 * @var string
 * @access public
 */
	var $tasks = array('Statistics', 'Admin');

/**
 * _welcome
 *
 * @access protected
 * @return void
 */
	function _welcome() {
		$this->out('Queue Plugin Shell');
		$this->hr();
	}

/**
 * main
 *
 * @access public
 * @return void
 */
	function main() {
		if ($this->args) {
			$worker = strpos($this->args[0], '_worker') !== false;
			$producer = strpos($this->args[0], '_producer') !== false;

			if ($worker || $producer) {
				return $this->_executeTask(array_shift($this->args));
			}
		}

		$this->out('[P]roducer');
		$this->out('[W]orker');
		$this->out('[A]dmin');
		$this->out('[S]tatistics');
		$this->out('[H]elp');
		$this->out('[Q]uit');

		$action = strtoupper($this->in('What would you like to do?', array('W', 'P', 'S', 'A', 'H', 'Q'),'q'));
		switch($action) {
			case 'W':
			case 'P':
				$prompt = sprintf('Please enter the name of the %s:',
					$action == 'W' ? 'worker' : 'producer'
				);
				$name = $this->in($prompt, null, 'debug');
				$this->_executeTask($name . ($action == 'W' ? '_worker' : '_producer'));
				break;
			case 'S':
				$this->Statistics->execute();
				break;
			case 'H':
				$this->help();
				break;
			case 'A':
				$this->Admin->execute();
				break;
			case 'Q':
				$this->_stop();
		}
		$this->main();
	}

	function _executeTask($name) {
		$name = Inflector::camelize($name);

		if (!isset($this->{$name})) {
			$this->tasks[] = $name;
			$this->loadTasks();
			$this->{$name}->initialize();
		}
		return $this->{$name}->execute();
	}

/**
 * Displays help contents
 *
 * @access public
 */
	function help() {
		// 63 chars ===============================================================
		$this->out('');
		$this->hr();
		$this->out('Usage: cake <params> queue <command> <args>');
		$this->hr();
		$this->out('Parameters:');
		$this->out("\t-verbose");
		$this->out("\t-quiet");
		$this->out('');
		$this->out('Commands:');
		$this->out("\n\thelp\n\t\tShows this help message.");
		$this->out("\n\debug_producer <tube>\n\t\tStart debug producer.");
		$this->out("\n\debug_worker <tubes>\n\t\tStart debug worker.");
		$this->out("\n\media_worker <tubes>\n\t\tStart media worker.");
		$this->out("\n\tstatistics\n\t\tPrint statistics.");
		$this->out('');
		$this->out('Arguments:');
		$this->out("\t<tube>\n\t\tTubes to use.");
		$this->out("\t<tubes>\n\t\tComma separated list of tubes to watch.");
		$this->out('');
	}
}
?>