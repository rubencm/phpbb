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

namespace phpbb\db\migration\data\v330;

class storage extends \phpbb\db\migration\migration
{
	public function update_data()
	{
		return array(
			array('config.add', array('storage_attachment', 'phpbb\\storage\\provider\\local')),
			array('config.add', array('storage_avatar', 'phpbb\\storage\\provider\\local')),
			array('config.add', array('storage_backup', 'phpbb\\storage\\provider\\local')),
			array('config.add', array('storage/avatar/config/path', $this->config['avatar_path'])),
			array('config.add', array('storage/attachment/config/path', $this->config['upload_path'])),
			array('config.add', array('storage/backup/config/path', 'store')),
			// I still dont remove this lines because some things use it and i cannot run the test forum
			//array('config.remove', array('avatar_path')),
			//array('config.remove', array('upload_path')),
		);
	}
}
