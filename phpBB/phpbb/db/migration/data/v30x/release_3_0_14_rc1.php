<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\db\migration\data\v30x;

class release_3_0_14_rc1 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return phpbb_version_compare($this->config['version'], '3.0.14-RC1', '>=') && phpbb_version_compare($this->config['version'], '3.1.0-dev', '<');
	}

	public static function depends_on()
	{
		return array('\phpbb\db\migration\data\v30x\release_3_0_13');
	}

	public function update_data()
	{
		return array(
			array('if', array(
				phpbb_version_compare($this->config['version'], '3.0.14-RC1', '<'),
				array('config.update', array('version', '3.0.14-RC1')),
			)),
		);
	}
}
