<?php

namespace phpbb\storage;

use phpbb\config\config;
use phpbb\di\service_collection;

class helper
{
	/** @var config */
	protected $config;

	/** @var service_collection */
	protected $provider_collection;

	public function __construct(config $config, service_collection $provider_collection)
	{
		$this->config = $config;
		$this->provider_collection = $provider_collection;
	}

	/**
	 * Get adapter definitions from a provider
	 *
	 * @param string $provider Provider class
	 * @return array Adapter definitions
	 */
	public function get_provider_options(string $provider) : array
	{
		return $this->provider_collection->get_by_class($provider)->get_options();
	}

	/**
	 * Get the current provider from config
	 *
	 * @param string $storage_name Storage name
	 * @return string The current provider
	 */
	public function get_current_provider(string $storage_name) : string
	{
		return $this->config['storage\\' . $storage_name . '\\provider'];
	}

	/**
	 * Get the current value of the definition of a storage from config
	 *
	 * @param string $storage_name Storage name
	 * @param string $definition Definition
	 * @return string Definition value
	 */
	public function get_current_definition(string $storage_name, string $definition) : string
	{
		return $this->config['storage\\' . $storage_name . '\\config\\' . $definition];
	}

	/**
	 * Get current storage adapter
	 *
	 * @param string $storage_name Storage adapter name
	 *
	 * @return object Storage adapter instance
	 */
	public function get_current_adapter(string $storage_name): object
	{
		static $adapters = [];

		if (!isset($adapters[$storage_name]))
		{
			$provider = $this->get_current_provider($storage_name);
			$provider_class = $this->provider_collection->get_by_class($provider);

			$adapter = $this->adapter_collection->get_by_class($provider_class->get_adapter_class());
			$definitions = $this->storage_helper->get_provider_options($provider);

			$options = [];
			foreach (array_keys($definitions) as $definition)
			{
				$options[$definition] = $this->get_current_definition($storage_name, $definition);
			}

			$adapter->configure($options);
			//$adapter->set_storage($storage_name);

			$adapters[$storage_name] = $adapter;
		}

		return $adapters[$storage_name];
	}

	/**
	 * Get new storage adapter
	 *
	 * @param string $storage_name
	 *
	 * @return object Storage adapter instance
	 */
	public function get_new_adapter(string $storage_name) : object
	{
		static $adapters = [];

		if (!isset($adapters[$storage_name]))
		{
			$provider = $this->state['storages'][$storage_name]['provider'];
			$provider_class = $this->provider_collection->get_by_class($provider);

			$adapter = $this->adapter_collection->get_by_class($provider_class->get_adapter_class());
			$definitions = $this->storage_helper->get_provider_options($provider);

			$options = [];
			foreach (array_keys($definitions) as $definition)
			{
				$options[$definition] = $this->state['storages'][$storage_name]['config'][$definition];
			}

			$adapter->configure($options);
			//$adapter->set_storage($storage_name);

			$adapters[$storage_name] = $adapter;
		}

		return $adapters[$storage_name];
	}

	public function delete_storage_options(string $storage_name)
	{
		$provider = $this->storage_helper->get_current_provider($storage_name);
		$options = $this->storage_helper->get_provider_options($provider);

		foreach (array_keys($options) as $definition)
		{
			$this->config->delete('storage\\' . $storage_name . '\\config\\' . $definition);
		}
	}

	public function set_storage_provider(string $storage_name, string $provider)
	{
		$this->config->set('storage\\' . $storage_name . '\\provider', $provider);
	}

	public function set_storage_definition(string $storage_name, string $definition, string $value)
	{
		$this->config->set('storage\\' . $storage_name . '\\config\\' . $definition, $value);
	}

	public function copy_new_adapter($storage_name, $file)
	{
		$current_adapter = $this->get_current_adapter($storage_name);
		$new_adapter = $this->get_new_adapter($storage_name);

		$stream = $current_adapter->read_stream($file);
		$new_adapter->write_stream($file, $stream);

		if (is_resource($stream)) {
			fclose($stream);
		}
	}

}
