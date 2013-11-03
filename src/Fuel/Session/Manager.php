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
	 * @var  mixed  $rotationInterval  Session id rotation interval, or false if rotation is disabled
	 *
	 * @since 2.0.0
	 */
	protected $rotationInterval;

	/**
	 * @var  integer  $rotationTimer  Unix timestamp of the last session Id rotation
	 *
	 * @since 2.0.0
	 */
	protected $rotationTimer;

	/**
	 * Setup a session instance
	 */
	public function __construct(Driver $driver, Array $config = array())
	{
		// store the driver and config
		$this->driver = $driver;
		$this->config = $config;

		// tell the driver who manages the session
		$this->driver->setManager($this);

		// create the containers
		$this->reset();

		// set the session rotation interval and the default rotation timer
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
			$this->rotationTimer = time() + $this->rotationInterval;
		}

		// any flash namespace defined?
		if (isset($config['flash_namespace']))
		{
			$this->setFlashNamespace($config['flash_namespace']);
		}

		// flash auto expiration defined?
		if (isset($config['flash_auto_expire']) and $config['flash_auto_expire'] == false)
		{
			$this->flash->setExpiryOnGet();
		}
		else
		{
			$this->flash->setExpiryOnRequest();
		}
	}

	/**
	 * Magic method, captures calls to the containers
	 */
	public function __call($method , Array $arguments)
	{
		// is this a flash method?
		if (substr($method, -5) == 'Flash')
		{
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
		// reset the data containers
		$this->reset();

		// and create a new session
		return $this->driver->create($this->data, $this->flash);
	}

	/**
	 * start a session
	 *
	 * @return	bool
	 */
	public function start()
	{
		return $this->driver->start($this->data, $this->flash);
	}

	/**
	 * read the session data into the session store
	 *
	 * @return	void
	 */
	public function read()
	{
		return $this->driver->read($this->data, $this->flash);
	}

	/**
	 * write the container data to the session store
	 *
	 * @return	void
	 */
	public function write()
	{
		// do we need to rotate?
		if ($this->rotationInterval and $this->rotationTimer < time())
		{
			$this->rotate();
		}

		return $this->driver->write($this->data, $this->flash);
	}

	/**
	 * start a session
	 *
	 * @return	bool
	 */
	public function stop()
	{
		// do we need to rotate?
		if ($this->rotationInterval and $this->rotationTimer < time())
		{
			$this->rotate();
		}

		// stop the current session
		return $this->driver->stop($this->data, $this->flash);
	}

	/**
	 * rotate the session id, and reset the rotation time if needed
	 */
	public function rotate()
	{
		// update the session id rotation timer
		if ($this->rotationInterval)
		{
			$this->rotationTimer = time() + $this->rotationInterval;
		}

		// regenerate the session id
		$this->driver->regenerate($this);
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

	/**
	 * get the current rotation time
	 *
	 * @access	public
	 * @return	int
	 */
	public function getRotationTimer()
	{
		return $this->rotationTimer;
	}

	/**
	 * get the defined rotation interval
	 *
	 * @access	public
	 * @param	int	 unix timestamp the last time the id was rotated
	 */
	public function setRotationTimer($time)
	{
		$this->rotationTimer = $time;
	}

	// --------------------------------------------------------------------

	/**
	 * set the session flash namespace
	 *
	 * @param	string	name of the namespace to set
	 * @access	public
	 * @return	Fuel\Core\Session_Driver
	 */
	public function setFlashNamespace($name)
	{
		$this->config['flash_namespace'] = (string) $name;

		$this->flash->setNamespace($this->config['flash_namespace']);

		return $this;
	}

	/**
	 * get the current session flash namespace
	 *
	 * @access	public
	 * @return	string	name of the flash namespace
	 */
	public function getFlashNamespace()
	{
		return $this->config['flash_namespace'];
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

		// initialise the flash expiry store
		$this->flash->set(FlashContainer::EXPIRE_DATA_KEY, array());

	}
}
