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

use phpbb\config\config;
use phpbb\config\db_text as config_text;
use phpbb\db\driver\driver_interface;
use phpbb\di\service_collection;
use phpbb\language\language;
use phpbb\log\log_interface;
use phpbb\path_helper;
use phpbb\request\request;
use phpbb\storage\helper;
use phpbb\storage\state_helper;
use phpbb\storage\update_type;
use phpbb\template\template;
use phpbb\user;

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

class acp_storage
{
	/** @var config $config */
	protected $config;

	/** @var config_text $config_text */
	protected $config_text;

	/** @var driver_interface $db */
	protected $db;

	/** @var language $log */
	protected $lang;

	/** @var log_interface $log */
	protected $log;

	/** @var path_helper $path_helper */
	protected $path_helper;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var service_collection */
	protected $adapter_collection;

	/** @var service_collection */
	protected $provider_collection;

	/** @var service_collection */
	protected $storage_collection;

	/** @var \phpbb\filesystem\filesystem */
	protected $filesystem;

	/** @var string */
	public $page_title;

	/** @var string */
	public $phpbb_root_path;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $u_action;

	/** @var state_helper */
	private $state_helper;

	/** @var helper */
	private $storage_helper;

	/**
	 * @param string $id
	 * @param string $mode
	 */
	public function main(string $id, string $mode)
	{
		global $phpbb_container, $phpbb_dispatcher, $phpbb_root_path;

		$this->config = $phpbb_container->get('config');
		$this->config_text = $phpbb_container->get('config_text');
		$this->db = $phpbb_container->get('dbal.conn');
		$this->filesystem = $phpbb_container->get('filesystem');
		$this->lang = $phpbb_container->get('language');
		$this->log = $phpbb_container->get('log');
		$this->path_helper = $phpbb_container->get('path_helper');
		$this->request = $phpbb_container->get('request');
		$this->template = $phpbb_container->get('template');
		$this->user = $phpbb_container->get('user');
		$this->adapter_collection = $phpbb_container->get('storage.adapter_collection');
		$this->provider_collection = $phpbb_container->get('storage.provider_collection');
		$this->storage_collection = $phpbb_container->get('storage.storage_collection');
		$this->phpbb_root_path = $phpbb_root_path;
		$this->state_helper = $phpbb_container->get('storage.state_helper');
		$this->storage_helper = $phpbb_container->get('storage.helper');

		// Add necesary language files
		$this->lang->add_lang(['acp/storage']);

		/**
		 * Add language strings
		 *
		 * @event core.acp_storage_load
		 * @since 3.3.0-a1
		 */
		$phpbb_dispatcher->trigger_event('core.acp_storage_load');

		@ini_set('memory_limit', '128M');

		switch ($mode)
		{
			case 'settings':
				$this->settings($id, $mode);
			break;
		}
	}

	/**
	 * @param string $id
	 * @param string $mode
	 */
	public function settings(string $id, string $mode): void
	{
		$action = $this->request->variable('action', '');

		if ($action && !$this->request->is_set_post('cancel'))
		{
			switch ($action)
			{
				case 'progress_bar':
					$this->display_progress_bar();
				break;

				case 'update':
					$this->update_action($id, $mode, $action);
				break;

				default:
					trigger_error('NO_ACTION', E_USER_ERROR);
			}
		}
		else
		{
			// If clicked to cancel (acp_storage_update_progress form)
			if ($this->request->is_set_post('cancel'))
			{
				$this->state_helper->clear_state();
			}

			// There is an updating in progress, show the form to continue or cancel
			if ($this->state_helper->is_action_in_progress())
			{
				$this->update_inprogress($id, $mode);
			}
			else
			{
				$this->settings_form($id, $mode);
			}
		}
	}

	private function update_action(string $id, string $mode, $action)
	{
		if (!check_link_hash($this->request->variable('hash', ''), 'acp_storage'))
		{
			trigger_error($this->lang->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		// If update_type is copy or move, copy files from the old to the new storage
		if (in_array($this->state_helper->update_type(), [update_type::STORAGE_UPDATE_TYPE_COPY, update_type::STORAGE_UPDATE_TYPE_MOVE], true))
		{
			$i = 0;
			foreach ($this->state['storages'] as $storage_name => $storage_options)
			{
				// Skip storages that have already moved files // todo: reescribir
				if ($this->state['storage_index'] > $i)
				{
					$i++;
					continue;
				}

				$sql = 'SELECT file_id, file_path
						FROM ' . STORAGE_TABLE . "
						WHERE  storage = '" . $this->db->sql_escape($storage_name) . "'
							AND file_id > " . (int) $this->state['file_index'];
				$result = $this->db->sql_query($sql);

				while ($row = $this->db->sql_fetchrow($result))
				{
					if (!still_on_time())
					{
						$this->save_state(); // esto quiza no haga falta si guardo el estado siempre
						meta_refresh(1, append_sid($this->u_action . '&amp;action=update&amp;hash=' . generate_link_hash('acp_storage')));
						trigger_error($this->lang->lang('self::STORAGE_UPDATE_REDIRECT', $this->lang->lang('STORAGE_' . strtoupper($storage_name) . '_TITLE'), $i + 1, count($this->state['storages'])));
					}

					// Copy file from old adapter to the new one
					$this->storage_helper->copy_new_adapter($storage_name, $row['file_path']);

					$this->state['file_index'] = $row['file_id']; // Set last uploaded file
				}

				$this->db->sql_freeresult($result);

				// Copied all files of a storage, increase storage index and reset file index
				$this->state['storage_index']++;
				$this->state['file_index'] = 0;
			}

			// If update_type is move files, remove the old files
			// todo: sacar este if fuera quiza
			if ($this->state_helper->update_type() === update_type::STORAGE_UPDATE_TYPE_MOVE)
			{
				$i = 0;
				foreach ($this->state['storages'] as $storage_name => $storage_options)
				{
					// Skip storages that have already moved files
					if ($this->state['remove_storage_index'] > $i)
					{
						$i++;
						continue;
					}

					$current_adapter = $this->storage_helper->get_current_adapter($storage_name);

					$sql = 'SELECT file_id, file_path
							FROM ' . STORAGE_TABLE . "
							WHERE  storage = '" . $this->db->sql_escape($storage_name) . "'
								AND file_id > " . (int) $this->state['file_index'];
					$result = $this->db->sql_query($sql);

					while ($row = $this->db->sql_fetchrow($result))
					{
						if (!still_on_time())
						{
							$this->save_state();
							meta_refresh(1, append_sid($this->u_action . '&amp;action=update&amp;hash=' . generate_link_hash('acp_storage')));
							trigger_error($this->lang->lang('STORAGE_UPDATE_REMOVE_REDIRECT', $this->lang->lang('STORAGE_' . strtoupper($storage_name) . '_TITLE'), $i + 1, count($this->state['storages'])));
						}

						$current_adapter->delete($row['file_path']);

						$this->state['file_index'] = $row['file_id']; // Set last uploaded file
					}

					$this->db->sql_freeresult($result);

					// Remove all files of a storage, increase storage index and reset file index
					$this->state['remove_storage_index']++;
					$this->state['file_index'] = 0;
				}
			}
		}

		// Here all files have been copied/moved, so save new configuration
		foreach (array_keys($this->state['storages']) as $storage_name)
		{
			$this->update_storage_config($storage_name);
		}

		$storages = array_keys($this->state['storages']);
		$this->state = false;
		$this->save_state();

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_STORAGE_UPDATE', false, $storages);
		trigger_error($this->lang->lang('STORAGE_UPDATE_SUCCESSFUL') . adm_back_link($this->u_action) . $this->close_popup_js());
	}

	private function update_inprogress(string $id, string $mode)
	{
		// Template from adm/style
		$this->tpl_name = 'acp_storage_update_inprogress';

		// Set page title
		$this->page_title = 'STORAGE_TITLE';

		$this->template->assign_vars(array(
			'UA_PROGRESS_BAR'		=> addslashes(append_sid($this->path_helper->get_phpbb_root_path() . $this->path_helper->get_adm_relative_path() . "index." . $this->path_helper->get_php_ext(), "i=$id&amp;mode=$mode&amp;action=progress_bar")),
			'U_CONTINUE_UPDATING'	=> $this->u_action . '&amp;action=update&amp;hash=' . generate_link_hash('acp_storage'),
			'L_CONTINUE'			=> $this->lang->lang('CONTINUE_UPDATING'),
			'L_CONTINUE_EXPLAIN'	=> $this->lang->lang('CONTINUE_UPDATING_EXPLAIN'),
		));
	}

	private function settings_form(string $id, string $mode)
	{
		$form_key = 'acp_storage';

		$this->storage_stats(); // Show table with storage stats

		// Process form and create a "state" for the update,
		// then show a confirm form
		$messages = [];

		if ($this->request->is_set_post('submit'))
		{
			// TODO: Check
			if (!check_form_key($form_key) || !check_link_hash($this->request->variable('hash', ''), 'acp_storage'))
			{
				trigger_error($this->lang->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$modified_storages = $this->get_modified_storages(); // Todo: messages por referencia de validate_path

			if(!empty($messages)) // todo: ver si tiene sentido (probablemente si, ya que get_modified_storages valida los datos introducidos)
			{
				trigger_error(implode('<br>', $messages) . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if (!empty($modified_storages))
			{
				// Create state
				$this->state_helper->init((int) $this->request->variable('update_type', self::STORAGE_UPDATE_TYPE_CONFIG), $modified_storages, $this->request);

				// Show the confirmation form to start the process
				$this->template->assign_vars(array(
					'UA_PROGRESS_BAR'		=> addslashes(append_sid($this->path_helper->get_phpbb_root_path() . $this->path_helper->get_adm_relative_path() . "index." . $this->path_helper->get_php_ext(), "i=$id&amp;mode=$mode&amp;action=progress_bar")), // same
					'S_CONTINUE_UPDATING'	=> true,
					'U_CONTINUE_UPDATING'	=> $this->u_action . '&amp;action=update&amp;hash=' . generate_link_hash('acp_storage'),
					'L_CONTINUE'			=> $this->lang->lang('START_UPDATING'),
					'L_CONTINUE_EXPLAIN'	=> $this->lang->lang('START_UPDATING_EXPLAIN'),
				));

				return;
			}

			// If there is no changes
			trigger_error($this->lang->lang('STORAGE_NO_CHANGES') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		// Template from adm/style
		$this->tpl_name = 'acp_storage';

		// Set page title
		$this->page_title = 'STORAGE_TITLE';

		$this->template->assign_vars([
			'STORAGES'						=> $this->storage_collection,
			'PROVIDERS' 					=> $this->provider_collection,

			'ERROR_MESSAGES'				=> $messages,

			'U_ACTION'						=> $this->u_action . '&amp;hash=' . generate_link_hash('acp_storage'),

			'STORAGE_UPDATE_TYPE_CONFIG'	=> self::STORAGE_UPDATE_TYPE_CONFIG,
			'STORAGE_UPDATE_TYPE_COPY'		=> self::STORAGE_UPDATE_TYPE_COPY,
			'STORAGE_UPDATE_TYPE_MOVE'		=> self::STORAGE_UPDATE_TYPE_MOVE,
		]);
	}

	private function get_modified_storages() {
		$modified_storages = [];

		foreach ($this->storage_collection as $storage)
		{
			$storage_name = $storage->get_name();

			$options = $this->storage_helper->get_provider_options($this->storage_helper->get_current_provider($storage_name));

			$messages = []; // todo: borrar
			$this->validate_path($storage_name, $options, $messages);

			$modified = false;

			// Check if provider have been modified
			if ($this->request->variable([$storage_name, 'provider'], '') != $this->storage_helper->get_current_provider($storage_name))
			{
				$modified = true;
			}
			else
			{ // Check if options have been modified
				foreach (array_keys($options) as $definition)
				{
					if ($this->request->variable([$storage_name, $definition], '') != $this->storage_helper->get_current_definition($storage_name, $definition))
					{
						$modified = true;
						break;
					}
				}
			}

			// If the storage have been modified, validate options
			if ($modified)
			{
				$modified_storages[] = $storage_name;
				$this->validate_data($storage_name, $messages); // todo: revisar
			}
		}

		return $modified_storages;
	}

	protected function storage_stats()
	{
		// Top table with stats of each storage
		$storage_stats = [];
		foreach ($this->storage_collection as $storage)
		{
			$storage_name = $storage->get_name();
			$options = $this->storage_helper->get_provider_options($this->storage_helper->get_current_provider($storage_name));

			$messages = [];
			$this->validate_path($storage_name, $options, $messages); // todo: esto no se deberia validar, ya que los datos se obtienen de la db creo

			try
			{
				$free_space = get_formatted_filesize($storage->free_space());
			}
			catch (\phpbb\storage\exception\exception $e)
			{
				$free_space = $this->lang->lang('STORAGE_UNKNOWN');
			}

			$storage_stats[] = [
				'name' => $this->lang->lang('STORAGE_' . strtoupper($storage->get_name()) . '_TITLE'),
				'files' => $storage->get_num_files(),
				'size' => get_formatted_filesize($storage->get_size()),
				'free_space' => $free_space,
			];
		}

		$this->template->assign_vars([
			'STORAGE_STATS' => $storage_stats,
		]);
	}

	/**
	 * Display progress bar
	 */
	protected function display_progress_bar() : void
	{
		adm_page_header($this->lang->lang('STORAGE_UPDATE_IN_PROGRESS'));
		$this->template->set_filenames(array(
				'body'	=> 'progress_bar.html')
		);
		$this->template->assign_vars(array(
				'L_PROGRESS'			=> $this->lang->lang('STORAGE_UPDATE_IN_PROGRESS'),
				'L_PROGRESS_EXPLAIN'	=> $this->lang->lang('STORAGE_UPDATE_IN_PROGRESS_EXPLAIN'))
		);
		adm_page_footer();
	}

	/**
	 * Get JS code for closing popup
	 *
	 * @return string Popup JS code
	 */
	function close_popup_js() : string
	{
		return "<script type=\"text/javascript\">\n" .
			"// <![CDATA[\n" .
			"	close_waitscreen = 1;\n" .
			"// ]]>\n" .
			"</script>\n";
	}

	/**
	 * Validates data
	 *
	 * @param string $storage_name Storage name
	 * @param array $messages Reference to messages array
	 */
	protected function validate_data(string $storage_name, array &$messages)
	{
		$storage_title = $this->lang->lang('STORAGE_' . strtoupper($storage_name) . '_TITLE');

		// Check if provider exists
		try
		{
			$new_provider = $this->provider_collection->get_by_class($this->request->variable([$storage_name, 'provider'], ''));
		}
		catch (\Exception $e)
		{
			$messages[] = $this->lang->lang('STORAGE_PROVIDER_NOT_EXISTS', $storage_title);
			return;
		}

		// Check if provider is available
		if (!$new_provider->is_available())
		{
			$messages[] = $this->lang->lang('STORAGE_PROVIDER_NOT_AVAILABLE', $storage_title);
			return;
		}

		// Check options
		$new_options = $this->storage_helper->get_provider_options($this->request->variable([$storage_name, 'provider'], ''));

		foreach ($new_options as $definition_key => $definition_value)
		{
			$provider = $this->provider_collection->get_by_class($this->request->variable([$storage_name, 'provider'], ''));
			$definition_title = $this->lang->lang('STORAGE_ADAPTER_' . strtoupper($provider->get_name()) . '_OPTION_' . strtoupper($definition_key));

			$value = $this->request->variable([$storage_name, $definition_key], '');

			switch ($definition_value['type'])
			{
				case 'email':
					if (!filter_var($value, FILTER_VALIDATE_EMAIL))
					{
						$messages[] = $this->lang->lang('STORAGE_FORM_TYPE_EMAIL_INCORRECT_FORMAT', $definition_title, $storage_title);
					}
					// no break

				case 'text':
				case 'password':
					$maxlength = isset($definition_value['maxlength']) ? $definition_value['maxlength'] : 255;
					if (strlen($value) > $maxlength)
					{
						$messages[] = $this->lang->lang('STORAGE_FORM_TYPE_TEXT_TOO_LONG', $definition_title, $storage_title);
					}
				break;

				case 'radio':
				case 'select':
					if (!in_array($value, array_values($definition_value['options'])))
					{
						$messages[] = $this->lang->lang('STORAGE_FORM_TYPE_SELECT_NOT_AVAILABLE', $definition_title, $storage_title);
					}
				break;
			}
		}
	}

	/**
	 * Updates a storage with the info provided in the form (that is stored in the state at this point)
	 *
	 * @param string $storage_name Storage name
	 */
	protected function update_storage_config(string $storage_name) : void
	{
		// Remove old storage config
		$this->storage_helper->delete_storage_options($storage_name);

		// Update provider
		$new_provider = $this->state_helper->get_new_provider($storage_name);
		$this->storage_helper->set_storage_provider($storage_name, $new_provider);

		// Set new storage config
		$new_options = $this->storage_helper->get_provider_options($new_provider);

		foreach (array_keys($new_options) as $definition)
		{
			$new_definition_value = $this->state_helper->get_new_definition_value($storage_name, $definition);
			$this->storage_helper->set_storage_definition($storage_name, $definition, $new_definition_value);
		}
	}

	/**
	 * Validates path
	 *
	 * @param string $storage_name Storage name
	 * @param array $options Storage provider configuration keys
	 * @param array $messages Error messages array
	 * @return void
	 */
	protected function validate_path(string $storage_name, array $options, array &$messages) : void
	{
		if ($this->provider_collection->get_by_class($this->storage_helper->get_current_provider($storage_name))->get_name() == 'local' && isset($options['path']))
		{
			$path = $this->request->is_set_post('submit') ? $this->request->variable([$storage_name, 'path'], '') : $this->storage_helper->get_current_definition($storage_name, 'path');

			if (empty($path))
			{
				$messages[] = $this->lang->lang('STORAGE_PATH_NOT_SET', $this->lang->lang('STORAGE_' . strtoupper($storage_name) . '_TITLE'));
			}
			else if (!$this->filesystem->is_writable($this->phpbb_root_path . $path) || !$this->filesystem->exists($this->phpbb_root_path . $path))
			{
				$messages[] = $this->lang->lang('STORAGE_PATH_NOT_EXISTS', $this->lang->lang('STORAGE_' . strtoupper($storage_name) . '_TITLE'));
			}
		}
	}


}
