<?php
/**
 * @package    Fuel\Session
 * @version    2.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2015 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Session;

use Fuel\Common\DataContainer as Container;

/**
 * Session Data container
 */
class DataContainer extends Container
{
	/**
	 * @var string
	 */
	protected $namespace = false;

	/**
	 * Sets session namespace
	 *
	 * @param string $name
	 */
	public function setNamespace($name)
	{
		if ( ! is_bool($name))
		{
			$name = (string) $name;
		}

		$this->namespace = $name;
	}

	/**
	 * Checks if a key was set upon this bag's data
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function has($key)
	{
		return parent::has($this->prefixKey($key));
	}

	/**
	 * Returns a key's value from this bag's data
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		return parent::get($this->prefixKey($key), $default);
	}

	/**
	 * Sets a config value
	 *
	 * @param string  $key
	 * @param mixed   $value
	 * @param integer $expiry
	 *
	 * @throws  \RuntimeException
	 */
	public function set($key, $value, $expiry = null)
	{
		// make sure we have a valid key
		if ($key === null)
		{
			throw new \RuntimeException('No valid key passed to set()');
		}

		// and store the value passed
		parent::set($this->prefixKey($key), $value);
	}

	/**
	 * Deletes data from the container
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function delete($key)
	{
		// and delete the key itself
		return parent::delete($this->prefixKey($key));
	}

	/**
	 * Prefixws the container key with the namespace currently set
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	protected function prefixKey($key)
	{
		// prefix the key with the namespace
		return empty($this->namespace) ? $key : $this->namespace.'.'.$key;
	}
}
