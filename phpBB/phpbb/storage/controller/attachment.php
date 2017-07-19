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
use phpbb\user;

class attachment
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

	protected $user;

	/**
	 * @var string
	 */
	protected $attachments_table;

	/**
	* Constructor.
	*
	* @param ContainerInterface $container A ContainerInterface instance
	*/
	public function __construct(config $config, ContainerInterface $container, driver_interface $db, request $request, storage $storage, user $user, $attachments_table)
	{
		$this->config = $config;
		$this->container = $container;
		$this->db = $db;
		$this->request = $request;
		$this->storage = $storage;
		$this->user = $user;
		$this->attachments_table = $attachments_table;
	}

	/**
	 * Controller for /download/attachment/{id} routes
	 *
	 * @param int		$id		ID of the entity to report
	 * @return \Symfony\Component\HttpFoundation\Response a Symfony response object
	 * @throws \phpbb\exception\http_exception when $mode or $id is invalid for some reason
	 */
	public function handle($id, $thumbnail = false)
	{
		global $cache, $auth;

		$mode = '';

		if(!function_exists('phpbb_download_handle_forum_auth'))
		{
			global $phpbb_root_path;
			include $phpbb_root_path.'includes/functions_download.php';
		}

		$this->sun_check(); // https://github.com/phpbb/phpbb/commit/bf59a749c3346ed7341d03946b8ecd0701af9eb8

		//if(!$this->attachments_enabled())
		//{
		//	send_status_line(404, 'Not Found');
		//	trigger_error('ATTACHMENT_FUNCTIONALITY_DISABLED');
		//}

		$attachment = $this->get_attachment($id);

		if(!$attachment)
		{
			throw new http_exception(404, 'ERROR_NO_ATTACHMENT');
		}

		if(!$this->is_download_allowed())
		{
			throw new http_exception(403, 'LINKAGE_FORBIDDEN');
		}

		if (!$attachment['in_message'] && !$this->config['allow_attachments'] || $attachment['in_message'] && !$this->config['allow_pm_attach'])
		{
			throw new http_exception(404, 'ATTACHMENT_FUNCTIONALITY_DISABLED');
		}

		if ($attachment['is_orphan'])
		{
			// We allow admins having attachment permissions to see orphan attachments...
			$own_attachment = ($auth->acl_get('a_attach') || $attachment['poster_id'] == $this->user->data['user_id']) ? true : false;

			if (!$own_attachment || ($attachment['in_message'] && !$auth->acl_get('u_pm_download')) || (!$attachment['in_message'] && !$auth->acl_get('u_download')))
			{
				throw new http_exception(404, 'ERROR_NO_ATTACHMENT');
			}

			// Obtain all extensions...
			$extensions = $cache->obtain_attach_extensions(true);
		}
		else
		{
			if (!$attachment['in_message'])
			{

				phpbb_download_handle_forum_auth($this->db, $auth, $attachment['topic_id']);

				$sql = 'SELECT forum_id, post_visibility
					FROM ' . POSTS_TABLE . '
					WHERE post_id = ' . (int) $attachment['post_msg_id'];
				$result = $this->db->sql_query($sql);
				$post_row = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				if (!$post_row || ($post_row['post_visibility'] != ITEM_APPROVED && !$auth->acl_get('m_approve', $post_row['forum_id'])))
				{
					// Attachment of a soft deleted post and the user is not allowed to see the post
					throw new http_exception(404, 'ERROR_NO_ATTACHMENT');
				}
			}
			else
			{
				// Attachment is in a private message.
				$post_row = array('forum_id' => false);
				phpbb_download_handle_pm_auth($this->db, $auth, $this->user->data['user_id'], $attachment['post_msg_id']);
			}

			$extensions = array();
			if (!extension_allowed($post_row['forum_id'], $attachment['extension'], $extensions))
			{
				throw new http_exception(403, sprintf($this->user->lang['EXTENSION_DISABLED_AFTER_POSTING'], $attachment['extension']));
			}
		}

		$attachment['physical_filename'] = utf8_basename($attachment['physical_filename']); //?


		$download_mode = (int) $extensions[$attachment['extension']]['download_mode'];
		$display_cat = $extensions[$attachment['extension']]['display_cat'];

		if (($display_cat == ATTACHMENT_CATEGORY_IMAGE || $display_cat == ATTACHMENT_CATEGORY_THUMB) && !$this->user->optionget('viewimg'))
		{
			$display_cat = ATTACHMENT_CATEGORY_NONE;
		}

		if ($display_cat == ATTACHMENT_CATEGORY_FLASH && !$this->user->optionget('viewflash'))
		{
			$display_cat = ATTACHMENT_CATEGORY_NONE;
		}

		if ($thumbnail)
		{
			$attachment['physical_filename'] = 'thumb_' . $attachment['physical_filename'];
		}
		else if ($display_cat == ATTACHMENT_CATEGORY_NONE && !$attachment['is_orphan'] && !phpbb_http_byte_range($attachment['filesize']))
		{
			// Update download count
			phpbb_increment_downloads($this->db, $attachment['attach_id']);
		}

		if ($display_cat == ATTACHMENT_CATEGORY_IMAGE && $mode === 'view' && (strpos($attachment['mimetype'], 'image') === 0) && (strpos(strtolower($this->user->browser), 'msie') !== false) && !phpbb_is_greater_ie_version($this->user->browser, 7))
		{
			wrap_img_in_html(append_sid($phpbb_root_path . 'download/file.' . $phpEx, 'id=' . $attachment['attach_id']), $attachment['real_filename']);
			file_gc();
		}
		else
		{
			// Determine the 'presenting'-method
			if ($download_mode == PHYSICAL_LINK)
			{
				// This presenting method should no longer be used
				if (!@is_dir($phpbb_root_path . $this->config['upload_path']))
				{
					throw new exception('PHYSICAL_DOWNLOAD_NOT_POSSIBLE');
				}

				redirect($phpbb_root_path . $this->config['upload_path'] . '/' . $attachment['physical_filename']);
				file_gc();
			}
			else
			{
				try
				{
					return $this->storage->download($attachment['physical_filename'], $attachment['real_filename']);
				}
				catch (exception $e)
				{
					throw new http_exception(404, 'ERROR_NO_ATTACHMENT');
				}
				file_gc();
			}
		}
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

	// Unnecessary method
	//protected function attachments_enabled()
	//{
	//	if (!$this->config['allow_attachments'] && !$this->config['allow_pm_attach'])
	//	{
	//		return false;
	//	}
	//
	//	return true;
	//}

	protected function get_attachment($id)
	{
		$sql = 'SELECT attach_id, post_msg_id, topic_id, in_message, poster_id, is_orphan, physical_filename, real_filename, extension, mimetype, filesize, filetime
			FROM ' . $this->attachments_table . "
			WHERE attach_id = $id";
		$result = $this->db->sql_query($sql);
		$attachment = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $attachment;
	}

	/**
	 * Check if downloading item is allowed
	 */
	protected function is_download_allowed()
	{
		return true;


		if (!$this->config['secure_downloads'])
		{
			return true;
		}

		$url = @parse_url(htmlspecialchars_decode($this->request->header('Referer')));

		if (!isset($url['host']))
		{
			return $this->config['secure_allow_empty_referer'] ? true : false;
		}

		$hostname = $url['host'];

		$server_name = $this->user->host;
		if ($this->config['force_server_vars'] || !$server_name)
		{
			$server_name = $this->config['server_name'];
		}

		//if (!$config['secure_allow_deny'] || preg_match('#^.*?' . preg_quote($server_name, '#') . '.*?$#i', $hostname))
		if(!$this->config['secure_allow_deny'] || stristr($hostname, $server_name))
		{
			return true;
		}

		// Get IP's and hostnames
		$iplist = @gethostbynamel($hostname);
		if ($iplist === null)
		{
			$iplist = [];
		}

		$sql = 'SELECT site_ip, site_hostname, ip_exclude
			FROM ' . SITELIST_TABLE;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->fetchrow($result))
		{
			$site_ip = trim($row['site_ip']);
			$site_hostname = trim($row['site_hostname']);

			if ($site_ip)
			{
				foreach ($iplist as $ip)
				{
					if (preg_match('#^' . str_replace('\*', '.*?', preg_quote($site_ip, '#')) . '$#i', $ip))
					{
						if ($row['ip_exclude'])
						{
							$allowed = ($this->config['secure_allow_deny']) ? false : true;
							break 2;
						}
						else
						{
							$allowed = ($this->config['secure_allow_deny']) ? true : false;
						}
					}
				}
			}

			if ($site_hostname)
			{
				if (preg_match('#^' . str_replace('\*', '.*?', preg_quote($site_hostname, '#')) . '$#i', $hostname))
				{
					if ($row['ip_exclude'])
					{
						$allowed = ($this->config['secure_allow_deny']) ? false : true;
						break;
					}
					else
					{
						$allowed = ($this->config['secure_allow_deny']) ? true : false;
					}
				}
			}

			$this->db->sql_freeresult($result);
		}

		return $alowed;
	}
}
