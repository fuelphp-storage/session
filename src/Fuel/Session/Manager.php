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

/**
 * Session manager class
 *
 * @package  Fuel\Session
 *
 * @since  2.0.0
 */
class Manager
{
	/**
	 * @var  Driver  $driver  Storage driver used by this session instance
	 *
	 * @since 2.0.0
	 */
	protected $driver;

	/**
	 * Passed configuration
	 *
	 * @since 2.0.0
	 */
	protected $config;

	/**
	 * @var  DataContainer  $data  Data storage containter for this session instance
	 *
	 * @since 2.0.0
	 */
	protected $data;

	/**
	 * @var  FlashContainer  $flash  Flash data storage containter for this session instance
	 *
	 * @since 2.0.0
	 */
	protected $flash;

	/**
	 * @var  mixed  $rotationTime  Session id rotation interval, or false if rotation is disabled
	 *
	 * @since 2.0.0
	 */
	protected $rotationInterval;

	/**
	 * @var  integer  $rotationTime  Unix timestamp of the last session Id rotation
	 *
	 * @since 2.0.0
	 */
	protected $rotationTime;

	/**
	 * Setup a session instance
	 */
	public function __construct(Driver $driver, Array $config = array())
	{
		// store the driver and config
		$this->driver = $driver;
		$this->config = $config;

		// create the containers
		$this->reset();

		// define the session rotation time
		if ( ! isset($config['rotation_time']))
		{
			$this->rotationInterval = 300;
		}
		elseif ( ! is_numeric($config['rotation_time']) or $config['rotation_time'] <= 0 or $config['rotation_time'] === false)
		{
			$this->rotationInterval = false;
		}
		else
		{
			$this->rotationInterval = (int) $config['rotation_time'];
		}
		$this->rotationTime = time();

		// any flash id defined?
		if (isset($config['flash_id']))
		{
			$this->setFlashId($config['flash_id']);
		}

		// flash auto expiration defined?
		if (isset($config['flash_auto_expire']))
		{
			$this->flash->setAutoExpire((bool) $config['flash_auto_expire']);
		}

		// flash expire after get defined?
		if (isset($config['flash_expire_after_get']))
		{
			$this->flash->setExpireAfterGet((bool) $config['flash_expire_after_get']);
		}
	}

	/**
	 * Magic method, captures calls to the containers
	 */
	public function __call($method , Array $arguments)
	{
		// do we need to rotate?
		if ($this->rotationInterval and $this->rotationTime < time())
		{
			$this->rotate();
		}

		// is this a flash method?
		if (substr($method, -5) == 'Flash')
		{
			if (is_callable(array($this->flash, $method)))
			{
				return call_user_func_array(array($this->flash, $method), $arguments);
			}

			$flashmethod = substr($method, 0, strlen($method)-5);

			if (is_callable(array($this->flash, $flashmethod)))
			{
				return call_user_func_array(array($this->flash, $flashmethod), $arguments);
			}
		}

		// data method
		elseif (is_callable(array($this->data, $method)))
		{
			return call_user_func_array(array($this->data, $method), $arguments);
		}

		throw new \BadMethodCallException('Method Session::'.$method.'() does not exists');
	}

	/**
	 * create a new session
	 *
	 * @return	bool
	 */
	public function create()
	{
		$this->reset();

		// and create a new session
		return $this->driver->create($this, $this->data, $this->flash);
	}

	/**
	 * start a session
	 *
	 * @return	bool
	 */
	public function start()
	{
		if ($result = $this->driver->start($this, $this->data, $this->flash))
		{
			// always rotate when starting the session
			$this->rotate();
		}

		return $result;
	}

	/**
	 * read the session data into the session store
	 *
	 * @return	void
	 */
	public function read()
	{
		return $this->driver->read($this, $this->data, $this->flash);
	}

	/**
	 * write the container data to the session store
	 *
	 * @return	void
	 */
	public function write()
	{
		return $this->driver->write($this, $this->data, $this->flash);
	}

	/**
	 * start a session
	 *
	 * @return	bool
	 */
	public function stop()
	{
		return $this->driver->stop($this, $this->data, $this->flash);
	}

	/**
	 * rotate the session id
	 *
	 * @return	bool
	 */
	public function rotate()
	{
		return $this->driver->regenerate($this);
	}

	/**
	 * destroy the current session
	 *
	 * @return	void
	 */
	public function destroy()
	{
		$this->reset();

		return $this->driver->destroy($this);
	}

	/**
	 * get session key variables
	 *
	 * @access	public
	 * @param	string	name of the variable to get, default is 'SessionId'
	 * @return	mixed
	 */
	public function getKey($name = 'SessionId')
	{
		return $this->driver->getKey($name);
	}

	// --------------------------------------------------------------------

	/**
	 * set the session flash id
	 *
	 * @param	string	name of the id to set
	 * @access	public
	 * @return	Fuel\Core\Session_Driver
	 */
	public function setFlashId($name)
	{
		$this->config['flash_id'] = (string) $name;

		$this->flash->setId($this->config['flash_id']);

		return $this;
	}

	/**
	 * get the current session flash id
	 *
	 * @access	public
	 * @return	string	name of the flash id
	 */
	public function getFlashId()
	{
		return $this->config['flash_id'];
	}

	/**
	 * Reset the data and flash data containers
	 */
	protected function reset()
	{
		// create the data container
		$this->data = new DataContainer();

		// create the flash container
		$this->flash = new FlashContainer();

	}
}
