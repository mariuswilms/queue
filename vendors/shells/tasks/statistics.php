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
/**
 * Make Task Class
 *
 * @package    media
 * @subpackage media.shells.tasks
 */
class StatisticsTask extends ManageShell {
	var $uses = array('Queue.Job');

	function execute() {
		$this->heading('Statistics');
		$this->out('Updating every 5 seconds');
		$this->out('Press STRG+C to abort');

		while (true) {
			$result = $this->Job->statistics();
			$this->out('Got:');
			$this->out(var_export($result, true));
			sleep(5);
			$this->clear();
		}
	}
}
?>
