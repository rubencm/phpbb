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

class state_helper
{
// update_type, storages, storage_index

	public function __construct()
	{

	}

	/**
	 * Returns if there is an action in progress
	 *
	 * @return bool
	 */
	public function is_action_in_progress(): bool
	{
		return !empty($this->config['search_indexing_state']);
	}

	// getters


	/**
	 * Start a indexing or delete process.
	 *
	 * @param string $search_type
	 * @param string $action
	 *
	 * @throws action_in_progress_exception  If there is an action in progress
	 * @throws no_search_backend_found_exception If search backend don't exist
	 * @throws search_exception If action isn't valid
	 */
	public function init(int $update_type): void
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
			'storage_index' => 0,
			'file_index' => 0,
			'remove_storage_index' => 0,
		];

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
	private function load_state(): array
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
	private function save_state(array $state = []): void
	{
		$this->config_text->set('storage_update_state', json_encode($state, JSON_THROW_ON_ERROR));
	}



}
