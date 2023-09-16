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

namespace phpbb\storage;

use phpbb\config\config;
use phpbb\request\request;
use phpbb\storage\exception\action_in_progress_exception;
use phpbb\storage\exception\no_action_in_progress_exception;

class state_helper
{
	public const STORAGE_UPDATE_TYPE_CONFIG = 0;
	public const STORAGE_UPDATE_TYPE_COPY = 1;
	public const STORAGE_UPDATE_TYPE_MOVE = 2;

	/** @var config */
	protected $config;

	/** @var helper */
	protected $storage_helper;

// update_type, storages, storage_index

	public function __construct(config $config, helper $storage_helper)
	{
		$this->config = $config;
		$this->storage_helper = $storage_helper;
	}

	/**
	 * Returns if there is an action in progress
	 *
	 * @return bool
	 */
	public function is_action_in_progress(): bool
	{
		return !empty($this->config['storage_update_state']);
	}

	// getters
	public function get_new_provider($storage_name)
	{
		$state = $this->load_state();

		return $state['storages'][$storage_name]['provider'];
	}

	public function get_new_definition_value($storage_name, $definition)
	{
		$state = $this->load_state();

		return $state['storages'][$storage_name]['config'][$definition];
	}

	public function update_type(): update_type
	{
		$state = $this->load_state();

		return update_type::from($state['update_type']);
	}


	/**
	 * Start a indexing or delete process.
	 *
	 * @param string $search_type
	 * @param string $action
	 *
	 * @throws action_in_progress_exception  If there is an action in progress
	 * @throws no_search_backend_found_exception If search backend don't exist
	 * @throws search_exception If action isn't valid
	 * @throws \JsonException
	 */
	public function init(int $update_type, array $modified_storages, request $request): void
	{
		// Is not possible to start a new process when there is one already running
		if ($this->is_action_in_progress())
		{
			throw new action_in_progress_exception();
		}

		// mas validaciones, comprobar que los parametros son validos

		$state = [
			// Save the value of the checkbox, to remove all files from the
			// old storage once they have been successfully moved
			'update_type' => $update_type,
			'storages' => [],
			'storage_index' => 0,
			'file_index' => 0,
			'remove_storage_index' => 0,
		];

		// Save in the state the selected storages and their new configuration
		foreach ($modified_storages as $storage_name)
		{
			$state['storages'][$storage_name] = [];

			$state['storages'][$storage_name]['provider'] = $request->variable([$storage_name, 'provider'], '');

			$options = $this->storage_helper->get_provider_options($request->variable([$storage_name, 'provider'], ''));

			foreach (array_keys($options) as $definition)
			{
				$state['storages'][$storage_name]['config'][$definition] = $request->variable([$storage_name, $definition], '');
			}
		}

		$this->save_state($state);
	}



	// update_counter

	/**
	 * Clear the state
	 *
	 * @throws \JsonException
	 */
	public function clear_state(): void
	{
		$this->save_state([]);
	}

	/**
	 * Load the state from the database
	 *
	 * @return array
	 *
	 * @throws no_action_in_progress_exception If there is no action in progress
	 */
	public function load_state(): array // todo: hacer privado
	{
		// Is not possible to execute an action over state if is empty
		if (!$this->is_action_in_progress())
		{
			throw new no_action_in_progress_exception();
		}

		return json_decode($this->config_text->get('storage_update_state'), true, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * Save the specified state in the database
	 *
	 * @param array $state
	 *
	 * @throws \JsonException
	 */
	public function save_state(array $state = []): void // todo: hacer privado
	{
		$this->config_text->set('storage_update_state', json_encode($state, JSON_THROW_ON_ERROR));
	}



}
