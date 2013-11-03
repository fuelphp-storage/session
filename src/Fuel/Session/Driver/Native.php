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
use Fuel\Session\Manager;
use Fuel\Session\DataContainer;
use Fuel\Session\FlashContainer;

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
		elseif (isset($config['expiration_time']))
		{
			$params['lifetime'] = $config['expiration_time'];
		}
		else
		{
			$params['lifetime'] = 7200;
		}

		session_set_cookie_params($params['lifetime'], $params['path'], $params['domain'], $params['secure'], $params['httponly']);

		// store the defined name
		if (isset($config['native']['cookie_name']))
		{
			$this->setName($config['native']['cookie_name']);
		}
	}

    /**
     * Create a new session
     *
     * @param  Manager $manager
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the start operation
	 * @since  2.0.0
     */
    public function create(Manager $manager, DataContainer $data, FlashContainer $flash)
    {
		// start the native session if we don't have one active yet
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			$this->start($manager, $data, $flash);
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
     * @param  Manager $manager
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the start operation
	 * @since  2.0.0
     */
    public function start(Manager $manager, DataContainer $data, FlashContainer $flash)
    {
		// start the native session
		if (session_status() !== 2)
		{
			session_start();
		}

		// update the stored id
		$this->setSessionId(session_id());

		// and read any existing session data
		return $this->read($manager, $data, $flash);
	}

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
    public function read(Manager $manager, DataContainer $data, FlashContainer $flash)
    {
		// bail out if we don't have an active session
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			return false;
		}

		// else fetch the data from the native session global
		elseif (isset($_SESSION['data']) and isset($_SESSION['flash']))
		{
			$data->setContents($_SESSION['data']);
			$flash->setContents($_SESSION['flash']);
		}

		return true;
	}

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
    public function write(Manager $manager, DataContainer $data, FlashContainer $flash)
    {
		// not implemented in the native driver, flush the data through a stop/start
		$this->stop($manager, $data, $flash);
		$this->start($manager, $data, $flash);
	}

    /**
     * Stop the session
     *
     * @param  Manager $manager
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the write operation
	 * @since  2.0.0
     */
    public function stop(Manager $manager, DataContainer $data, FlashContainer $flash)
    {
		// bail out if we don't have an active session
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			return false;
		}

		// write and close the session
		else
		{
			$_SESSION = array(
				'data' => $data->getContents(),
				'flash' => $flash->getContents(),
			);

			session_write_close();
		}

		return true;
	}

    /**
     * Destroy the session
     *
     * @param  Manager $manager
     *
     * @return bool  result of the write operation
	 * @since  2.0.0
     */
    public function destroy(Manager $manager)
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
				$params = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000,
					$params['path'], $params['domain'],
					$params['secure'], $params['httponly']
				);
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
     * @param  Manager $manager
     *
	 * @since  2.0.0
     */
    public function regenerate(Manager $manager)
    {
		// regenerate the session id
		session_regenerate_id();

		// and update the stored id
		$this->setSessionId(session_id());
	}

	/**
	 * Set the global expiration of the entire session
	 */
	public function setExpire($expiry)
	{
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
