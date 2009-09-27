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
	var $tasks = array('Statistics');

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
		$this->out('[P]roducer');
		$this->out('[W]orker');
		$this->out('[S]tatistics');
		$this->out('[H]elp');
		$this->out('[Q]uit');

		$action = strtoupper($this->in('What would you like to do?', array('W', 'P', 'S', 'H', 'Q'),'q'));
		switch($action) {
			case 'W':
			case 'P':
				$prompt = sprintf('Please enter the name of the %s:',
					$action == 'W' ? 'worker' : 'producer'
				);
				$name = $this->in($prompt, null, 'debug');
				$name = Inflector::camelize($name) . ($action == 'W' ? 'Worker' : 'Producer');

				if (!isset($this->{$name})) {
					$this->tasks[] = $name;
					$this->loadTasks();
					$this->{$name}->initialize();
				}
				$this->{$name}->execute();
				break;
			case 'S':
				$this->Statistics->execute();
				break;
			case 'H':
				$this->help();
				break;
			case 'Q':
				$this->_stop();
		}
		$this->main();
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
		$this->out('Params:');
		$this->out("\t-verbose");
		$this->out("\t-quiet");
		$this->out();
		$this->out('Commands:');
		$this->out("\n\thelp\n\t\tShows this help message.");
		$this->out("\n\tstatistics\n\t\tPrint statistics.");
		$this->out('');
	}
}
?>