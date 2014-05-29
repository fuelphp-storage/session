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
	 * @var  array  Passed configuration
	 *
	 * @since 2.0.0
	 */
	protected $config;

	/**
	 * @var  Fuel\Foundation\Applidation  $app  Application instance
	 *
	 * @since 2.0.0
	 */
	protected $app;

	/**
	 * @var  DataContainer  $data  Data storage container for this session instance
	 *
	 * @since 2.0.0
	 */
	protected $data;

	/**
	 * @var  FlashContainer  $flash  Flash data storage container for this session instance
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
	 *
	 * @since 2.0.0
	 */
	public function __construct(Driver $driver, Array $config = array(), $app = null)
	{
		// store the driver and config
		$this->driver = $driver;
		$this->config = $config;
		$this->app = $app;

		// create the containers
		$this->reset();

		// tell the driver who manages the session, and what the containers are
		$this->driver->setInstances($this, $this->data, $this->flash);

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

		// any namespace defined?
		if (isset($config['namespace']))
		{
			if ($config['namespace'] === true)
			{
				$config['namespace'] = $app ? $app->getName() : false;
			}
			$this->setNamespace($config['namespace']);
		}

		// any flash namespace defined?
		if (isset($config['flash_namespace']))
		{
			$this->setFlashNamespace($config['flash_namespace']);
		}

		// flash auto expiration defined?
		if (isset($config['flash_auto_expire']) and $config['flash_auto_expire'] == false)
		{
			// by default, expire flash when getting the flash variable
			$this->flash->setExpiryOnGet();
		}
		else
		{
			// by default, expire flash on the next session load
			$this->flash->setExpiryOnRequest();
		}
	}

	/**
	 * Magic method, captures calls to the containers and the driver
	 *
	 * @param  string  $method     name of the method being called
	 * @param  array   $arguments  arguments to pass on to the method
	 *
	 * @return  mixed
	 *
	 * @since 2.0.0
	 */
	public function __call($method , Array $arguments)
	{
		// is this a flash method?
		if (substr($method, -5) == 'Flash')
		{
			$method = substr($method, 0, strlen($method)-5);

			if (is_callable(array($this->flash, $method)))
			{
				return call_user_func_array(array($this->flash, $method), $arguments);
			}
		}

		// data method?
		elseif (is_callable(array($this->data, $method)))
		{
			return call_user_func_array(array($this->data, $method), $arguments);
		}

		// driver method?
		elseif (substr($method, 0, 3) == 'get' and is_callable(array($this->driver, $method)))
		{
			return call_user_func_array(array($this->driver, $method), $arguments);
		}

		throw new \BadMethodCallException('Method Session::'.$method.'() does not exists');
	}

	/**
	 * Create a new session
	 *
     * @return bool  result of the start operation
     *
	 * @since 2.0.0
	 */
	public function create()
	{
		// reset the data containers
		$this->reset();

		// and create a new session
		return $this->driver->create();
	}

	/**
	 * Start a session
	 *
	 * @return	bool
	 *
	 * @since 2.0.0
	 */
	public function start()
	{
		return $this->driver->start();
	}

	/**
	 * Read the session data into the session store
	 *
	 * @return	bool
	 *
	 * @since 2.0.0
	 */
	public function read()
	{
		return $this->driver->read();
	}

	/**
	 * Write the container data to the session store
	 *
	 * @return	bool
	 *
	 * @since 2.0.0
	 */
	public function write()
	{
		// do we need to rotate?
		if ($this->rotationInterval and $this->rotationTimer < time())
		{
			$this->rotate();
		}

		return $this->driver->write();
	}

	/**
	 * Start a session
	 *
	 * @return	bool
	 *
	 * @since 2.0.0
	 */
	public function stop()
	{
		// do we need to rotate?
		if ($this->rotationInterval and $this->rotationTimer < time())
		{
			$this->rotate();
		}

		// stop the current session
		return $this->driver->stop();
	}

	/**
	 * Rotate the session id, and reset the rotation timer if needed
	 *
	 * @since 2.0.0
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
	 * Destroy the current session
	 *
	 * @since 2.0.0
	 */
	public function destroy()
	{
		$this->reset();

		return $this->driver->destroy($this);
	}

	/**
	 * Get the current rotation interval timer
	 *
	 * @return	int
	 *
	 * @since 2.0.0
	 */
	public function getRotationTimer()
	{
		return $this->rotationTimer;
	}

	/**
	 * Set the rotation interval timer
	 *
	 * @param	int	 unix timestamp the last time the id was rotated
	 *
	 * @return	Fuel\Session\Manager
	 *
	 * @since 2.0.0
	 */
	public function setRotationTimer($time)
	{
		$this->rotationTimer = $time;

		return $this;
	}

	/**
	 * Set the session namespace
	 *
	 * @param	string	name of the namespace to set
	 *
	 * @return	Fuel\Session\Manager
	 *
	 * @since 2.0.0
	 */
	public function setNamespace($name)
	{
		$this->config['namespace'] = is_bool($name) ? $name : (string) $name;

		if ($this->config['namespace'] === true)
		{
			$this->config['namespace'] = $this->app ? $app->getName() : false;
		}

		$this->data->setNamespace($this->config['namespace']);

		return $this;
	}

	/**
	 * Get the current session namespace
	 *
	 * @return	string	name of the namespace
	 *
	 * @since 2.0.0
	 */
	public function getNamespace()
	{
		return $this->config['namespace'];
	}

	/**
	 * Set the session flash namespace
	 *
	 * @param	string	name of the namespace to set
	 *
	 * @return	Fuel\Session\Manager
	 *
	 * @since 2.0.0
	 */
	public function setFlashNamespace($name)
	{
		$this->config['flash_namespace'] = (string) $name;

		$this->flash->setNamespace($this->config['flash_namespace']);

		return $this;
	}

	/**
	 * Get the current session flash namespace
	 *
	 * @return	string	name of the flash namespace
	 *
	 * @since 2.0.0
	 */
	public function getFlashNamespace()
	{
		return $this->config['flash_namespace'];
	}

	/**
	 * Reset the data and flash data containers
	 *
	 * @since 2.0.0
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
