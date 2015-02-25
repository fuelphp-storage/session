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

use Fuel\Foundation\Applidation;

/**
 * Session manager class
 */
class Manager
{
	/**
	 * @var Driver
	 */
	protected $driver;

	/**
	 * @var array
	 */
	protected $config;

	/**
	 * @var Applidation
	 */
	protected $app;

	/**
	 * @var DataContainer
	 */
	protected $data;

	/**
	 * @var FlashContainer
	 */
	protected $flash;

	/**
	 * @var mixed
	 */
	protected $rotationInterval;

	/**
	 * @var integer
	 */
	protected $rotationTimer;

	/**
	 * @param Driver           $driver
	 * @param array            $config
	 * @param Application|null $app
	 */
	public function __construct(Driver $driver, array $config = [], Application $app = null)
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
	 * @param string $method
	 * @param array  $arguments
	 *
	 * @return mixed
	 *
	 * @since 2.0
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
	 * Creates a new session
	 *
     * @return boolean
	 */
	public function create()
	{
		// reset the data containers
		$this->reset();

		// and create a new session
		return $this->driver->create();
	}

	/**
	 * Starts a session
	 *
	 * @return boolean
	 */
	public function start()
	{
		return $this->driver->start();
	}

	/**
	 * Reads the session data into the session store
	 *
	 * @return boolean
	 */
	public function read()
	{
		return $this->driver->read();
	}

	/**
	 * Writes the container data to the session store
	 *
	 * @return boolean
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
	 * Stops a session
	 *
	 * @return boolean
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
	 * Rotates the session id, and reset the rotation timer if needed
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
	 * Destroys the current session
	 */
	public function destroy()
	{
		$this->reset();

		return $this->driver->destroy($this);
	}

	/**
	 * Returns the current rotation interval timer
	 *
	 * @return integer
	 */
	public function getRotationTimer()
	{
		return $this->rotationTimer;
	}

	/**
	 * Sets the rotation interval timer
	 *
	 * @param integer $time
	 */
	public function setRotationTimer($time)
	{
		$this->rotationTimer = $time;
	}

	/**
	 * Returns the current session namespace
	 *
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->config['namespace'];
	}

	/**
	 * Sets the session namespace
	 *
	 * @param string $name
	 */
	public function setNamespace($name)
	{
		$this->config['namespace'] = is_bool($name) ? $name : (string) $name;

		if ($this->config['namespace'] === true)
		{
			$this->config['namespace'] = $this->app ? $app->getName() : false;
		}

		$this->data->setNamespace($this->config['namespace']);
	}

	/**
	 * Returns the current session flash namespace
	 *
	 * @return string
	 */
	public function getFlashNamespace()
	{
		return $this->config['flash_namespace'];
	}

	/**
	 * Sets the session flash namespace
	 *
	 * @param string $name
	 */
	public function setFlashNamespace($name)
	{
		$this->config['flash_namespace'] = (string) $name;

		$this->flash->setNamespace($this->config['flash_namespace']);
	}

	/**
	 * Resets the data and flash data containers
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
