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
 * Session driver interface. All Session drivers must implement this
 * interface
 *
 * @package  Fuel\Session
 *
 * @since  2.0.0
 */
abstract class Driver
{
	/**
	 * @var  integer  $expiration Global session expiration
	 */
	protected $expiration = null;

	/**
	 * @var  string  $sessionid  ID used to identify this drivers session
	 */
	protected $sessionId = null;

	/**
	 * @var  string  $name  Name used to identify this session
	 */
	protected $name = null;

	/**
	 * Constructor
	 *
	 * @param  array    $config  driver configuration
	 * @since  2.0.0
	 */
	public function __construct(array $config = array())
	{
		if (isset($config['native']['cookie_name']))
		{
			$this->setName($config['native']['cookie_name']);
		}

		if (isset($config['expiration_time']) and is_numeric($config['expiration_time']) and $config['expiration_time'] > 0)
		{
			$this->setExpire($config['expiration_time']);
		}
	}

    /**
     * Create a new session
     *
     * @param  Manager $manager
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the create operation
	 * @since  2.0.0
     */
    abstract public function create(Manager $manager, DataContainer $data, FlashContainer $flash);

    /**
     * Start the session
     *
     * @param  Manager $manager
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the start operation
	 * @since  2.0.0
     */
    abstract public function start(Manager $manager, DataContainer $data, FlashContainer $flash);

    /**
     * Read session data
     *
     * @param  Manager $manager
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the write operation
	 * @since  2.0.0
     */
    abstract public function read(Manager $manager, DataContainer $data, FlashContainer $flash);

    /**
     * Write session data
     *
     * @param  Manager $manager
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the write operation
	 * @since  2.0.0
     */
    abstract public function write(Manager $manager, DataContainer $data, FlashContainer $flash);

    /**
     * Stop the session
     *
     * @param  Manager $manager
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the stop operation
	 * @since  2.0.0
     */
    abstract public function stop(Manager $manager, DataContainer $data, FlashContainer $flash);

    /**
     * Destroy the session
     *
     * @param  Manager $manager
     *
     * @return bool  result of the destroy operation
	 * @since  2.0.0
     */
    abstract public function destroy(Manager $manager);

    /**
     * Regerate the session, rotate the session id
     *
     * @param  Manager $manager
     *
     * @return bool  result of the regenerare operation
	 * @since  2.0.0
     */
    abstract public function regenerate(Manager $manager);

	/**
	 * Set the global expiration of the entire session
	 */
	public function setExpire($expiry)
	{
		$this->expiration = $expiry;
	}

	/**
	 * Get the global expiration of the entire session
	 */
	public function getExpire()
	{
		return $this->expiration;
	}

	/**
	 * Set the session ID for this session
	 */
	public function setSessionId($id)
	{
		$this->sessionId = $id;
	}

	/**
	 * Get the session ID for this session
	 */
	public function getSessionId()
	{
		return $this->sessionId;
	}

	/**
	 * Set the global name of this session
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * Get the global name of this session
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * get session key variables
	 *
	 * @param	string	name of the variable to get, default is 'session_id'
	 * @return	mixed
	 */
	public function getKey($name = 'SessionId')
	{
		if (method_exists($this, 'get'.$name))
		{
			return $this->{'get'.$name}();
		}
		elseif (property_exists($this, $name))
		{
			return $this->{$name};
		}

		return null;
	}

}
