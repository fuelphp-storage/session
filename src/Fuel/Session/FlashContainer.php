<?php
/**
 * @package    Fuel\Session
 * @version    2.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2013 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Session;

use Fuel\Common\DataContainer as Container;

/**
 * Session Flash data container
 *
 * @package  Fuel\Session
 *
 * @since  2.0.0
 */
class FlashContainer extends Container
{
	/**
	 * @var  array  $expiration variable expiration tracker
	 */
	protected $expiration = array();

	/**
	 * @var  string  $id   current flash id, used for variable namespacing
	 */
	protected $id = 'flash';

	/**
	 * @var  bool  $autoExpire   whether or not flash variables expire after the next load
	 */
	protected $autoExpire = true;

	/**
	 * @var  bool  $expireAfterGet   wether or not flash variables expire immediately after a get
	 */
	protected $expireAfterGet = false;

	/**
	 * set session flash id name
	 *
	 * @param	string	name of the flash id namespace
	 * @return	void
	 */
	public function setId($name)
	{
		$this->id = $name;
	}

	/**
	 * set session flash auto expiry
	 *
	 * @param	bool	whether or not flash variables auto expire
	 * @return	void
	 */
	public function setAutoExpire($expire = false)
	{
		$this->autoExpire = $expire;
	}

	/**
	 * set session flash expiry after a get
	 *
	 * @param	bool	whether or not flash variables expire after a get
	 * @return	void
	 */
	public function setExpireAfterGet($expire = false)
	{
		$this->expireAfterGet = $expire;
	}

	/**
	 * set session flash variables
	 *
	 * @param	string	name of the variable to set
	 * @param	mixed	value
	 * @access	public
	 * @return	void
	 */
	public function setFlash($name, $value = null)
	{
		// prefix the name with the flash id
		if ( ! empty($this->id))
		{
			$name = $this->id.'.'.$name;
		}

		$this->expiration[$name] = false;
		$this->set($name, $value);
	}

	/**
	 * get session flash variables
	 *
	 * @access	public
	 * @param	string	name of the variable to get
	 * @param	mixed	default value to return if the variable does not exist
	 * @param	bool	true if the flash variable needs to expire immediately
	 * @return	mixed
	 */
	public function getFlash($name = null, $default = null, $expire = null)
	{
		if ( ! is_bool($expire))
		{
			$expire = $this->expireAfterGet;
		}

		// prefix the name with the flash id
		if ( ! empty($this->id))
		{
			$name = $this->id.'.'.$name;
		}

		$value = $this->get($name, $default);

		if ($expire)
		{
			$this->expiration[$name] = true;
		}

		return $value;
	}

	/**
	 * keep session flash variables
	 *
	 * @access	public
	 * @param	string	name of the variable to keep
	 * @return	void
	 */
	public function keepFlash($name = null)
	{
		if ($name === null)
		{
			foreach (array_keys($this->expiration) as $name)
			{
				// if we have a flash id, only reset those that match
				if (empty($this->id) or strpos($name, $this->id.'.') === 0)
				{
					$this->expiration[$name] = false;
				}
			}
		}
		else
		{
			// prefix the name with the flash id
			if ( ! empty($this->id))
			{
				$name = $this->id.'.'.$name;
			}

			// reset the expiration if the key exists
			if (isset($this->expiration[$name]))
			{
				$this->expiration[$name] = false;
			}
		}
	}

	/**
	 * delete session flash variables
	 *
	 * @param	string	name of the variable to delete
	 * @param	mixed	value
	 * @access	public
	 * @return	void
	 */
	public function deleteFlash($name = null)
	{
		if ($name === null)
		{
			foreach (array_keys($this->expiration) as $name)
			{
				// if we have a flash id, only reset those that match
				if (empty($this->id) or strpos($name, $this->id.'.') === 0)
				{
					unset($this->expiration[$name]);
					$this->delete($name);
				}
			}
		}
		else
		{
			// prefix the name with the flash id
			if ( ! empty($this->id))
			{
				$name = $this->id.'.'.$name;
			}

			// reset the expiration if the key exists
			if (isset($this->expiration[$name]))
			{
				unset($this->expiration[$name]);
				$this->delete($name);
			}
		}
	}

	/**
	 * Replace the container's data.
	 *
	 * @param   array  $data  new data
	 * @return  $this
	 * @throws  RuntimeException
	 * @since   2.0.0
	 */
	public function setContents(array $data)
	{
		if ( ! isset($data['data']) or  ! isset($data['expiration']))
		{
			throw new \InvalidArgumentException('The Flash data container requires that you set both data and expiration information!');
		}

		parent::setContents($data['data']);

		$this->expiration = $data['expiration'];

		// existing data, expire it if needed
		foreach (array_keys($this->expiration) as $index)
		{
			$this->expiration[$index] = $this->autoExpire;
		}
	}

	/**
	 * Get the container's data
	 *
	 * @return  array  container's data
	 * @since   2.0.0
	 */
	public function getContents()
	{
		$data = $this->data;
		$expiration = $this->expiration;

		foreach($expiration as $name => $expired)
		{
			if ($expired)
			{
				unset($expiration[$name]);
				\Arr::delete($data, $name);
			}
		}

		return array('data' => $data, 'expiration' => $expiration);
	}
}
