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
	var $tasks = array('Producer', 'Statistics');
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
				$name = $this->in('Please enter the name of the worker:', null, 'debug');
				$name = Inflector::camelize($name) . 'Worker';

				if (!isset($this->{$name})) {
					$this->tasks[] = $name;
					$this->loadTasks();
					$this->{$name}->initialize();
				}
				$this->{$name}->execute();
				break;
			case 'P':
				$this->Producer->execute();
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
		$this->out('Checks if files in filesystem are in sync with records.');
		$this->hr();
		$this->out('Usage: cake <params> media.manage <command> <args>');
		$this->hr();
		$this->out('Params:');
		$this->out("\t-connection <name> Database connection to use.");
		$this->out("\t-yes Always assumes 'y' as answer.");
		$this->out("\t-filter <version> Restrict command to a specfic filter version (e.g. xxl).");
		$this->out("\t-force Overwrite files if they exist.");
		$this->out("\t-verbose");
		$this->out("\t-quiet");
		$this->out();
		$this->out('Commands:');
		$this->out("\n\thelp\n\t\tShows this help message.");
		$this->out("\n\tsynchron <model>\n\t\tChecks if records and files are in sync.");
		$this->out("\n\tmake <source> <destination>\n\t\tProcess a file or directory according to filters.");
		$this->out();
		$this->out('Args:');
		$this->out("\t<model> Name of the Model to use.");
		$this->out("\t<source> Absolute path to a file or directory.");
		$this->out("\t<destination> Absolute path to a directory.");
		$this->out();
	}
}
?>