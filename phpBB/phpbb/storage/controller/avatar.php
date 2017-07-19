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

namespace phpbb\storage\controller;

use phpbb\config\config;
use Symfony\Component\DependencyInjection\ContainerInterface;
use phpbb\db\driver\driver_interface;
use phpbb\exception\http_exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use phpbb\request\request;
use phpbb\storage\storage;

class avatar
{
	/**
	 * @var \phpbb\config\config
	 */
	protected $config;

	/**
	* @var ContainerInterface
	*/
	protected $container;

	/**
	 * @var \phpbb\db\driver\driver_interface
	 */
	protected $db;

	protected $request;

	protected $storage;

	/**
	 * @var string
	 */
	protected $attachments_table;

	/**
	* Constructor.
	*
	* @param ContainerInterface $container A ContainerInterface instance
	*/
	public function __construct(config $config, ContainerInterface $container, driver_interface $db, request $request, storage $storage)
	{
		$this->config = $config;
		$this->container = $container;
		$this->db = $db;
		$this->request = $request;
		$this->storage = $storage;
	}

	/**
	 * Controller for /download/attachment/{id} routes
	 *
	 * @param int		$id		ID of the entity to report
	 * @return \Symfony\Component\HttpFoundation\Response a Symfony response object
	 * @throws \phpbb\exception\http_exception when $mode or $id is invalid for some reason
	 */
	public function handle($file)
	{
		$this->sun_check(); // ??
		global $phpbb_root_path, $phpEx;
		require_once($phpbb_root_path . 'includes/constants.' . $phpEx);
		require_once($phpbb_root_path . 'includes/functions.' . $phpEx);
		require_once($phpbb_root_path . 'includes/functions_download' . '.' . $phpEx);
		require_once($phpbb_root_path . 'includes/utf/utf_tools.' . $phpEx);

		// worst-case default
		$browser = strtolower($this->request->header('User-Agent', 'msie 6.0'));

		$filename = $file;
		$avatar_group = false;
		$exit = false;

		if (isset($filename[0]) && $filename[0] === 'g')
		{
			$avatar_group = true;
			$filename = substr($filename, 1);
		}

		// '==' is not a bug - . as the first char is as bad as no dot at all
		if (strpos($filename, '.') == false)
		{
			throw new http_exception(403, 'Forbbiden');
			$exit = true;
		}

		if (!$exit)
		{
			$ext		= substr(strrchr($filename, '.'), 1);
			$stamp		= (int) substr(stristr($filename, '_'), 1);
			$filename	= (int) $filename;
			$exit = set_modified_headers($stamp, $browser);
		}
		if (!$exit && !in_array($ext, array('png', 'gif', 'jpg', 'jpeg')))
		{
			// no way such an avatar could exist. They are not following the rules, stop the show.
			throw new http_exception(403, 'Forbbiden');
			$exit = true;
		}


		if (!$exit)
		{
			if (!$filename)
			{
				// no way such an avatar could exist. They are not following the rules, stop the show.
				throw new http_exception(403, 'Forbbiden');
			}
			else
			{
				try
				{
					return $this->storage->download($this->config['avatar_salt'] . '_' . ($avatar_group ? 'g' : '') . $filename . '.' . $ext);
				}
				catch (exception $e)
				{
					throw new http_exception(404, 'ERROR_NO_ATTACHMENT');
				}
			}
		}
		file_gc();
	}

	protected function sun_check()
	{
		// Thank you sun.
		if ($this->request->header('content-type', false))
		{			if ($_SERVER['CONTENT_TYPE'] === 'application/x-java-archive')
			{
				exit;
			}
		}
		else if (strpos($this->request->header('User-Agent', ''), 'Java') !== false)
		{
			exit;
		}
	}

}
