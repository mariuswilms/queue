<?php
/**
 * Job Model File
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
 * @subpackage queue.models
 * @copyright  2009 David Persson <davidpersson@gmx.de>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/davidpersson/queue
 */

/**
 * Job Model Class
 *
 * @package    queue
 * @subpackage queue.models
 */
class Job extends QueueAppModel {

/**
 * Database configuration to use
 *
 * @var string
 * @access public
 */
	var $useDbConfig = 'queue';
}
?>