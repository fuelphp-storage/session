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
use Fuel\Common\Arr as Arr;

/**
 * @const  int  expire variable on next session load
 */
const EXPIRE_ON_REQUEST = 1;

/**
 * @const  int  expire variable on first get
 */
const EXPIRE_ON_GET = 2;

/**
 * Session Flash data container
 */
class FlashContainer extends Container
{
	/**
	 * @const  int  variable is stored in the current request
	 */
	const EXPIRE_STATE_NEW = 1;

	/**
	 * @const  int  variable is loaded from the session data store
	 */
	const EXPIRE_STATE_LOADED = 2;

	/**
	 * @const  int  variable is expired, and will be removed on save
	 */
	const EXPIRE_STATE_EXPIRED = 3;

	/**
	 * @const  string  key value used to store expiration information
	 */
	const EXPIRE_DATA_KEY = '__expiry__data__';

	/**
	 * @var  string  $namespace  current flash namespace
	 */
	protected $namespace = 'flash';

	/**
	 * @var  int  $defaultExpiryState  expiry state to assign to flash variables that don't have one
	 */
	protected $defaultExpiryState = 1;

	/**
	 * @var  array  $states  list of valid flash expiry states
	 */
	protected $expiryStates = [
		EXPIRE_ON_REQUEST,
		EXPIRE_ON_GET,
	];

	/**
	 * Sets session flash namespace
	 *
	 * @param string $name
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setNamespace($name)
	{
		$name = $name ?: '';

		if ( ! is_string($name))
		{
			throw new \InvalidArgumentException('Argument passed to setNamespace() must be a string value');
		}

		$this->namespace = $name;
	}

	/**
	 * Sets the default flash variable expiry to expire on next request
	 */
	public function setExpiryOnRequest()
	{
		$this->defaultExpiryState = EXPIRE_ON_REQUEST;
	}

	/**
	 * Sets the default flash variable expiry to expire on first get
	 */
	public function setExpiryOnGet()
	{
		$this->defaultExpiryState = EXPIRE_ON_GET;
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
	 * Reset the expiry state on the given key
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function keep($key)
	{
		if (isset($this->data[static::EXPIRE_DATA_KEY][$key]))
		{
			$this->data[static::EXPIRE_DATA_KEY][$key][1] = static::EXPIRE_STATE_NEW;
			return true;
		}

		return false;
	}

	/**
	 * Returns the expiry state on the given key
	 *
	 * @param string $key
	 *
	 * @return array
	 */
	public function expiration($key)
	{
		if (isset($this->data[static::EXPIRE_DATA_KEY][$key]))
		{
			$expiration = array(
				$this->data[static::EXPIRE_DATA_KEY][$key][0] == EXPIRE_ON_REQUEST ? 'EXPIRE_ON_REQUEST' : 'EXPIRE_ON_GET',
				$this->data[static::EXPIRE_DATA_KEY][$key][1] == static::EXPIRE_STATE_NEW ? 'STATE_NEW' : ($this->data[static::EXPIRE_DATA_KEY][$key][1] == static::EXPIRE_STATE_LOADED ? 'STATE_LOADED' : 'STATE_EXPIRED'),
			);
			return $expiration;
		}

		return false;
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
		// check if we have this key
		if (parent::has($this->prefixKey($key)))
		{
			// do we have expiry information?
			if ( ! isset($this->data[static::EXPIRE_DATA_KEY][$key]))
			{
				// nope, simply return the data
				return parent::get($this->prefixKey($key), $default);
			}

			// return the data if we don't have expiry information or if the data is valid
			if ($this->data[static::EXPIRE_DATA_KEY][$key][1] !== static::EXPIRE_STATE_EXPIRED)
			{
				// check the expiry strategy
				if ($this->data[static::EXPIRE_DATA_KEY][$key][0] === EXPIRE_ON_GET)
				{
					// expire on get, so expire it
					$this->data[static::EXPIRE_DATA_KEY][$key][1] = static::EXPIRE_STATE_EXPIRED;
				}

				// return the data
				return parent::get($this->prefixKey($key), $default);
			}
		}

		// no dice, return the default
		return $default;
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
			throw new \RuntimeException('No valid key passed to setFlash()');
		}

		// get the default expiry if none is given
		$expiry === null and $expiry = $this->defaultExpiryState;

		// validate the expiry
		if ( ! in_array($expiry, $this->expiryStates))
		{
			throw new \RuntimeException('"'.$expiry.'" is not a valid session flash variable expiration state.');
		}

		// store the expiry information for this key
		$this->data[static::EXPIRE_DATA_KEY][$key] = array($expiry, static::EXPIRE_STATE_NEW);

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
		// remove the expiration tracking for this key
		unset($this->data[static::EXPIRE_DATA_KEY][$key]);

		// and delete the key itself
		return parent::delete($this->prefixKey($key));
	}

	/**
	 * Replaces the container's data
	 *
	 * @param array $data
	 *
	 * @throws \RuntimeException
	 */
	public function setContents(array $data)
	{
		// make sure we have our expire data key
		if (isset($data[static::EXPIRE_DATA_KEY]))
		{
			// process the expiration settings
			foreach($data[static::EXPIRE_DATA_KEY] as $key => $expiration)
			{
				// if it was set on the last request, make it as loaded
				if ($expiration[1] === static::EXPIRE_STATE_NEW)
				{
					$data[static::EXPIRE_DATA_KEY][$key][1] = static::EXPIRE_STATE_LOADED;
				}

				// if it was already loaded on the last request, and we expire on request, delete it
				elseif ($expiration[0] === EXPIRE_ON_REQUEST and $expiration[1] === static::EXPIRE_STATE_LOADED)
				{
					unset($data[static::EXPIRE_DATA_KEY][$key]);
					\Arr::delete($data, $this->prefixKey($key));
				}
			}
		}
		else
		{
			// not set, create an empty one to start with
			$data[static::EXPIRE_DATA_KEY] = array();
		}

		// store the data
		parent::setContents($data);
	}

	/**
	 * Returns the container's data
	 *
	 * @return array
	 */
	public function getContents()
	{
		// make a copy to leave the original container untouched
		$data = $this->data;

		// delete all expired variables
		foreach($data[static::EXPIRE_DATA_KEY] as $key => $expiration)
		{
			if ($expiration[1] === static::EXPIRE_STATE_EXPIRED)
			{
				unset($data[static::EXPIRE_DATA_KEY][$key]);
				\Arr::delete($data, $this->prefixKey($key));
			}
		}

		// and return what's left over
		return $data;
	}

	/**
	 * Prefixes the container key with the flash namespace currently set
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	protected function prefixKey($key)
	{
		// prefix the key with the flash namespace
		return empty($this->namespace) ? $key : $this->namespace.'.'.$key;
	}
}
