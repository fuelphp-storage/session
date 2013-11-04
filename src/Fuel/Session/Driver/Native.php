<?php
/**
 * @package    Fuel\Session
 * @version    2.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2013 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Session\Driver;

use Fuel\Session\Driver;

/**
 * Session driver using PHP native sessions
 *
 * @package  Fuel\Session
 *
 * @since  2.0.0
 */
class Native extends Driver
{
	/**
	 * @var  array  session driver config defaults
	 */
	protected $defaults = array(
		'cookie_name'           => 'fuelnid',
	);

	/**
	 * Constructor
	 *
	 * @param  array    $config  driver configuration
	 *
	 * @since  2.0.0
	 */
	public function __construct(array $config = array())
	{
		// make sure we've got all config elements for this driver
		$config['native'] = array_merge($this->defaults, isset($config['native']) ? $config['native'] : array());

		// call the parent to process the global config
		parent::__construct($config);

		// get default the cookie params
		$params = session_get_cookie_params();

		// update them with any config passed
		if (isset($config['cookie_domain']))
		{
			$params['domain'] = $config['cookie_domain'];
		}

		if (isset($config['cookie_path']))
		{
			$params['path'] = $config['cookie_path'];
		}

		if (isset($config['cookie_secure']) and $config['cookie_secure'])
		{
			$params['secure'] = true;
		}

		if (isset($config['cookie_http_only']) and $config['cookie_http_only'])
		{
			$params['httponly'] = true;
		}

		if (isset($config['expire_on_close']) and $config['expire_on_close'])
		{
			$params['lifetime'] = 0;
		}

		session_set_cookie_params($this->expiration, $params['path'], $params['domain'], $params['secure'], $params['httponly']);

		// store the defined name
		if (isset($config['native']['cookie_name']))
		{
			$this->name = $config['native']['cookie_name'];
		}
	}

    /**
     * Create a new session
     *
     * @return bool  result of the start operation
     *
	 * @since  2.0.0
     */
    public function create()
    {
		// start the native session if we don't have one active yet
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			$this->start();
		}

		// regenerate the session id and flush any existing sessions
		if ($result = session_regenerate_id(true))
		{
			// and update the stored id
			$this->setSessionId(session_id());
		}

		return $result;
	}

    /**
     * Start the session, and read existing session data back
     *
     * @return bool  result of the start operation
     *
	 * @since  2.0.0
     */
    public function start()
    {
		// start the native session
		if (session_status() !== 2)
		{
			session_start();
		}

		// update the stored id
		$this->setSessionId(session_id());

		// and read any existing session data
		return $this->read();
	}

    /**
     * Read session data
     *
     * @return bool  result of the read operation
     *
	 * @since  2.0.0
     */
    public function read()
    {
		// bail out if we don't have an active session
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			return false;
		}

		// else fetch the data from the native session global
		elseif (isset($_SESSION[$this->name]))
		{
			return $this->processPayload($_SESSION[$this->name]);
		}

		return false;
	}

    /**
     * Write session data
     *
     * @return bool  result of the write operation
     *
	 * @since  2.0.0
     */
    public function write()
    {
		// not implemented in the native driver, flush the data through a stop/start
		$stop = $this->stop();
		$start = $this->start();

		// only return true if both succeeded
		return ($stop and $start);
	}

    /**
     * Stop the session
     *
     * @return bool  result of the write operation
	 *
	 * @since  2.0.0
     */
    public function stop()
    {
		// bail out if we don't have an active session
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			return false;
		}

		$_SESSION[$this->name] = $this->assemblePayload();

		session_write_close();

		return true;
	}

    /**
     * Destroy the session
     *
     * @return bool  result of the write operation
	 *
	 * @since  2.0.0
     */
    public function destroy()
    {
		// bail out if we don't have an active session
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			return false;
		}
		else
		{
			// delete the session cookie if present
			if (ini_get('session.use_cookies'))
			{
				$this->deleteCookie(session_name());
			}

			// unset all native session data
			session_unset();

			// and kill the session
			return session_destroy();
		}
	}

    /**
     * Regerate the session, rotate the session id
     *
	 * @since  2.0.0
     */
    public function regenerate()
    {
		// regenerate the session id
		session_regenerate_id();

		// and update the stored id
		$this->setSessionId(session_id());
	}

	/**
	 * Set the global expiration of the entire session
	 *
     * @param  int  session expiration time in seconds of inactivity
     *
	 * @since  2.0.0
	 */
	public function setExpire($expiry)
	{
		// set the expiry on the session cache
		session_cache_expire($expiry);

		// set the cookie expiry to match the expiration set
		session_set_cookie_params($expiry);

		return parent::setExpire($expiry);
	}

	/**
	 * Set the session ID for this session
	 */
	public function setSessionId($id)
	{
		session_id($id);

		return parent::setSessionId($id);
	}

	/**
	 * Set the global name of this session
	 */
	public function setName($name)
	{
		session_name($name);

		return parent::setName($name);
	}
}
