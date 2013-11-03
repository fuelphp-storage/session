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
	 * @var  array  $condig  Passed configuration array
	 */
	protected $config = array();

	/**
	 * @var  array  global session config defaults
	 */
	protected $globalDefaults = array(
		'match_ip'                  => false,
		'match_ua'                  => true,
		'cookie_domain'             => '',
		'cookie_path'               => '/',
		'cookie_secure'             => false,
		'cookie_http_only'          => null,
		'expire_on_close'           => false,
		'expiration_time'           => 7200,
		'rotation_time'             => 300,
		'flash_namespace'           => 'flash',
		'flash_auto_expire'         => true,
		'flash_expire_after_get'    => true,
		'post_cookie_name'          => '',
		'http_header_name'          => 'Session-Id',
		'enable_cookie'             => true,
	);

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
	protected $name = 'fuelsession';

	/**
	 * Constructor
	 *
	 * @param  array    $config  driver configuration
	 * @since  2.0.0
	 */
	public function __construct(array $config = array())
	{
		$config = array_merge($this->globalDefaults, $config);

		if (isset($config['expiration_time']) and is_numeric($config['expiration_time']) and $config['expiration_time'] > 0)
		{
			$this->setExpire($config['expiration_time']);
		}

		$this->config = $config;
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
     * @return bool  result of the read operation
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
	 * @since  2.0.0
     */
    public function regenerate(Manager $manager)
    {
		// store a fake session id
		$this->setSessionId(uniqid());
	}

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

	/**
	 * Sets a cookie. Note that all cookie values must be strings and no
	 * automatic serialization will be performed!
	 *
	 * @param   string    name of cookie
	 * @param   string    value of cookie
	 * @return  boolean
	 */
	protected function setCookie($name, $value)
	{
		// add the current time so we have an offset
		$expiration = $this->config['expiration_time'] > 0 ? $this->config['expiration_time'] + time() : 0;

		return setcookie($name, $value, $expiration, $this->config['cookie_path'], $this->config['cookie_domain'], $this->config['cookie_secure'], $this->config['cookie_http_only']);
	}

	/**
	 * Deletes a cookie by making the value null and expiring it.
	 *
	 * @param   string   cookie name
	 *
	 * @return  boolean
	 */
	protected function deleteCookie($name)
	{
		// Remove the cookie
		unset($_COOKIE[$name]);

		// Nullify the cookie and make it expire
		return setcookie($name, null, -86400, $this->config['cookie_path'], $this->config['cookie_domain'], $this->config['cookie_secure'], $this->config['cookie_http_only']);
	}
}
