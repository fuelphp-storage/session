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

use Fuel\Session\DataContainer;
use Fuel\Session\FlashContainer;

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
	 * @var  array  $config  Passed configuration array
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
		'post_cookie_name'          => '',
		'http_header_name'          => 'Session-Id',
		'enable_cookie'             => true,
	);

	/**
	 * @var  Manager  $manager  Session manager instance that manages this driver
	 */
	protected $manager = null;

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
	 * @var  integer  $expiration  Global session expiration
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
	 *
	 * @since  2.0.0
	 */
	public function __construct(array $config = array())
	{
		// make sure we've got all the config values
		$config = array_merge($this->globalDefaults, $config);

		// set the expiration inactivity timer. if invalid, default to 7200 seconds (2 hours)
		if (isset($config['expiration_time']) and is_numeric($config['expiration_time']) and $config['expiration_time'] > 0)
		{
			$this->setExpire($config['expiration_time']);
		}
		else
		{
			$this->setExpire(7200);
		}

		// store the config passed
		$this->config = $config;
	}

    /**
     * Create a new session
     *
     * @return bool  result of the create operation
     *
	 * @since  2.0.0
     */
    abstract public function create();

    /**
     * Start the session
     *
     * @return bool  result of the start operation
	 *
	 * @since  2.0.0
     */
    abstract public function start();

    /**
     * Read session data
     *
     * @return bool  result of the read operation
	 *
	 * @since  2.0.0
     */
    abstract public function read();

    /**
     * Write session data
     *
     * @return bool  result of the write operation
	 *
	 * @since  2.0.0
     */
    abstract public function write();

    /**
     * Stop the session
     *
     * @return bool  result of the stop operation
	 *
	 * @since  2.0.0
     */
    abstract public function stop();

    /**
     * Destroy the session
     *
     * @return bool  result of the destroy operation
	 *
	 * @since  2.0.0
     */
    abstract public function destroy();

    /**
     * Regenerate the session id
     *
	 * @since  2.0.0
     */
    public function regenerate()
    {
		// generate a new random session id
		$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$sessionId = '';
		for ($i=0; $i < 32; $i++)
		{
			$sessionId .= substr($pool, mt_rand(0, strlen($pool) -1), 1);
		}

		// store the new session id
		$this->setSessionId($sessionId);
	}

	/**
	 * Set the manager that manages this driver and container instances for this session
	 *
     * @param  Manager  instance
     *
	 * @since  2.0.0
	 */
	public function setInstances(Manager $manager, DataContainer $data, FlashContainer $flash)
	{
		$this->manager = $manager;
		$this->data = $data;
		$this->flash = $flash;
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
		$this->expiration = $expiry;
	}

	/**
	 * Get the global expiration of the entire session
     *
     * @return  int
     *
	 * @since  2.0.0
	 */
	public function getExpire()
	{
		return $this->expiration;
	}

	/**
	 * Set the session ID for this session
	 *
     * @param  string  $id  new id for this session
     *
	 * @since  2.0.0
	 */
	public function setSessionId($id)
	{
		$this->sessionId = $id;
	}

	/**
	 * Get the session ID for this session
     *
     * @return  string
     *
	 * @since  2.0.0
	 */
	public function getSessionId()
	{
		return $this->sessionId;
	}

	/**
	 * Set the global name of this session
	 *
	 * @param  string  $name  name of this session (and session cookie if present)
     *
	 * @since  2.0.0
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * Get the global name of this session
     *
     * @return  string
     *
	 * @since  2.0.0
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Find the current session id
     *
	 * @since  2.0.0
	 */
	protected function findSessionId()
	{
		// check for a posted session id
		if ( ! empty($this->config['post_cookie_name']) and isset($_POST[$this->config['post_cookie_name']]))
		{
			$this->sessionId = $_POST[$this->config['post_cookie_name']];
		}

		// else check for a regular cookie
		elseif ( ! empty($this->config['enable_cookie']) and isset($_COOKIE[$this->name]))
		{
			$this->sessionId = $_COOKIE[$this->name];
		}

		// else check for a session id in the URL
		elseif (isset($_GET[$this->name]))
		{
			$this->sessionId = $_GET[$this->name];
		}

		// TODO: else check the HTTP headers for the session id

		return $this->sessionId ?: null;
	}

	/**
	 * Process the session payload
     *
     * @param  array  $payload  retrieved raw session payload array
     *
     * @return bool
     *
	 * @since  2.0.0
	 */
	protected function processPayload(Array $payload)
	{
		// verify the payload
		if (isset($payload['security']) and isset($payload['data']) and isset($payload['flash']))
		{
			if (( ! $this->config['match_ip'] or $payload['security']['ip'] === $_SERVER['REMOTE_ADDR']) and
				( ! $this->config['match_ua'] or $payload['security']['ua'] === $_SERVER['HTTP_USER_AGENT']) and
				($payload['security']['ex'] == 0 or $payload['security']['ex'] >= time()) and
				$payload['security']['id'] == $this->sessionId)
			{
				// restore the session id
				$this->setSessionId($payload['security']['id']);

				// restore the last session id rotation timer
				$this->manager->setRotationTimer($payload['security']['rt']);

				// and store the data
				$this->data->setContents($payload['data']);
				$this->flash->setContents($payload['flash']);

				return true;
			}
		}

		return false;
	}

	/**
	 * Process the session payload
	 *
	 * @return  array  the assembled session payload array
     *
	 * @since  2.0.0
	 */
	protected function assemblePayload()
	{
		$expiration = $this->expiration > 0 ? $this->expiration + time() : 0;

		return array(
			'data' => $this->data->getContents(),
			'flash' => $this->flash->getContents(),
			'security' => array(
				'ip' => $_SERVER['REMOTE_ADDR'],
				'ua' => $_SERVER['HTTP_USER_AGENT'],
				'ex' => $expiration,
				'rt' => $this->manager->getRotationTimer(),
				'id' => $this->sessionId,
			),
		);
	}


	/**
	 * Sets a cookie. Note that all cookie values must be strings and no
	 * automatic serialization will be performed!
	 *
	 * @param   string    name of cookie
	 * @param   string    value of cookie
	 *
	 * @return  bool
     *
	 * @since  2.0.0
	 */
	protected function setCookie($name, $value)
	{
		// add the current time so we have an offset
		$expiration = $this->expiration > 0 ? $this->expiration + time() : 0;

		return setcookie($name, $value, $expiration, $this->config['cookie_path'], $this->config['cookie_domain'], $this->config['cookie_secure'], $this->config['cookie_http_only']);
	}

	/**
	 * Deletes a cookie by making the value null and expiring it.
	 *
	 * @param   string   cookie name
	 *
	 * @return  bool
     *
	 * @since  2.0.0
	 */
	protected function deleteCookie($name)
	{
		// Remove the cookie
		unset($_COOKIE[$name]);

		// Nullify the cookie and make it expire
		return setcookie($name, null, -86400, $this->config['cookie_path'], $this->config['cookie_domain'], $this->config['cookie_secure'], $this->config['cookie_http_only']);
	}
}
