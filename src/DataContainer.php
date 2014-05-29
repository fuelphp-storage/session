<?php
/**
 * @package    Fuel\Session
 * @version    2.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2014 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Session;

use Fuel\Common\DataContainer as Container;

/**
 * Session Data container
 *
 * @package  Fuel\Session
 *
 * @since  2.0.0
 */
class DataContainer extends Container
{
	/**
	 * @var  string  $namespace  current namespace
	 */
	protected $namespace = false;

	/**
	 * set session namespace
	 *
	 * @param  mixed  namespace
	 *
	 * @return  void
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
	 * Check if a key was set upon this bag's data
	 *
	 * @param   string  $key
	 *
	 * @return  bool
	 *
	 * @since   2.0.0
	 */
	public function has($key)
	{
		return parent::has($this->prefixKey($key));
	}

	/**
	 * Get a key's value from this bag's data
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 *
	 * @return  mixed
	 *
	 * @since   2.0.0
	 */
	public function get($key, $default = null)
	{
		return parent::get($this->prefixKey($key), $default);
	}

	/**
	 * Set a config value
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 * @param   int     $expiry  optional variable expiry override
	 *
	 * @throws  \RuntimeException
	 *
	 * @since   2.0.0
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
	 * Delete data from the container
	 *
	 * @param   string   $key  key to delete
	 *
	 * @return  boolean  delete success or failure
	 *
	 * @since   2.0.0
	 */
	public function delete($key)
	{
		// and delete the key itself
		return parent::delete($this->prefixKey($key));
	}

	/**
	 * Prefix the container key with the namespace currently set
	 *
	 * @param   string  $key
	 *
	 * @return   string  key
	 */
	protected function prefixKey($key)
	{
		// prefix the key with the namespace
		return empty($this->namespace) ? $key : $this->namespace.'.'.$key;
	}
}
