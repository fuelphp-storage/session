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

use Fuel\Session\DataContainer;
use Fuel\Session\FlashContainer;

/**
 * Session driver interface. All Session drivers must implement this
 * interface
 */
abstract class Driver
{
	/**
	 * @var array
	 */
	protected $config = [];

	/**
	 * @var array
	 */
	protected $globalDefaults = [
		'match_ip'          => false,
		'match_ua'          => true,
		'cookie_domain'     => '',
		'cookie_path'       => '/',
		'cookie_secure'     => false,
		'cookie_http_only'  => null,
		'expire_on_close'   => false,
		'expiration_time'   => 7200,
		'rotation_time'     => 300,
		'namespace'         => false,
		'flash_namespace'   => 'flash',
		'flash_auto_expire' => true,
		'post_cookie_name'  => '',
		'http_header_name'  => 'Session-Id',
		'enable_cookie'     => true,
	];

	/**
	 * @var Manager
	 */
	protected $manager;

	/**
	 * @var DataContainer
	 */
	protected $data;

	/**
	 * @var FlashContainer
	 */
	protected $flash;

	/**
	 * @var integer
	 */
	protected $expiration = null;

	/**
	 * @var string
	 */
	protected $sessionId = null;

	/**
	 * @var string
	 */
	protected $name = 'fuelsession';

	/**
	 * @param array $config
	 */
	public function __construct(array $config = [])
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
     * Creates a new session
     *
     * @return boolean
     */
    abstract public function create();

    /**
     * Starts the session
     *
     * @return boolean
     */
    abstract public function start();

    /**
     * Reads session data
     *
     * @return boolean
     */
    abstract public function read();

    /**
     * Writes session data
     *
     * @return boolean
     */
    abstract public function write();

    /**
     * Stops the session
     *
     * @return boolean
     */
    abstract public function stop();

    /**
     * Destroys the session
     *
     * @return boolean
     */
    abstract public function destroy();

    /**
     * Regenerates the session id
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
	 * Sets the manager that manages this driver and container instances for this session
	 *
     * @param Manager        $manager
     * @param DataContainer  $data
     * @param FlashContainer $data
	 */
	public function setInstances(Manager $manager, DataContainer $data, FlashContainer $flash)
	{
		$this->manager = $manager;
		$this->data = $data;
		$this->flash = $flash;
	}

	/**
	 * Returns the global expiration of the entire session
     *
     * @return integer
	 */
	public function getExpire()
	{
		return $this->expiration;
	}

	/**
	 * Sets the global expiration of the entire session
	 *
     * @param integer $expiry
	 */
	public function setExpire($expiry)
	{
		$this->expiration = $expiry;
	}

	/**
	 * Returns the session ID for this session
     *
     * @return string
	 */
	public function getSessionId()
	{
		return $this->sessionId;
	}

	/**
	 * Sets the session ID for this session
	 *
     * @param string  $id
	 */
	public function setSessionId($id)
	{
		$this->sessionId = $id;
	}

	/**
	 * Returns the global name of this session
     *
     * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Sets the global name of this session
	 *
	 * @param string $name
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * Finds the current session id
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
	 * Processes the session payload
     *
     * @param array $payload
     *
     * @return boolean
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
	 * Processes the session payload
	 *
	 * @return array
	 */
	protected function assemblePayload()
	{
		// calculate the expiration
		$expiration = $this->expiration > 0 ? $this->expiration + time() : 0;

		// make sure we have a sessionId
		if ($this->sessionId === null)
		{
			$this->regenerate();
		}

		// return the assembled payload
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
	 * @param string $name
	 * @param string $value
	 *
	 * @return boolean
	 */
	protected function setCookie($name, $value)
	{
		// add the current time so we have an offset
		$expiration = $this->expiration > 0 ? $this->expiration + time() : 0;

		return setcookie($name, $value, $expiration, $this->config['cookie_path'], $this->config['cookie_domain'], $this->config['cookie_secure'], $this->config['cookie_http_only']);
	}

	/**
	 * Deletes a cookie by making the value null and expiring it
	 *
	 * @param string $na,e
	 *
	 * @return boolean
	 */
	protected function deleteCookie($name)
	{
		// Remove the cookie
		unset($_COOKIE[$name]);

		// Nullify the cookie and make it expire
		return setcookie($name, null, -86400, $this->config['cookie_path'], $this->config['cookie_domain'], $this->config['cookie_secure'], $this->config['cookie_http_only']);
	}
}
