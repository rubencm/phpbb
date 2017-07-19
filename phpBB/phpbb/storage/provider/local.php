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

namespace phpbb\storage\provider;

use phpbb\storage\exception\exception;
use phpbb\filesystem\exception\filesystem_exception;

class local implements provider_interface
{
	public function get_class()
	{
		return \phpbb\storage\adapter\local::class;
	}

	public function get_options()
	{
		return ['path'];
	}
}
