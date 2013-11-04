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
 * Session driver using session emulation via cookies
 *
 * @package  Fuel\Session
 *
 * @since  2.0.0
 */
class Cookie extends Driver
{
	/**
	 * @var  array  session driver config defaults
	 */
	protected $defaults = array(
		'cookie_name'           => 'fuelcid',
		'encrypt_cookie'        => true,
		'crypt_key'             => '',
	);

	/**
	 * @var  bool  flag to indicate session state
	 */
	protected $started = false;

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
		$config['cookie'] = array_merge($this->defaults, isset($config['cookie']) ? $config['cookie'] : array());

		// call the parent to process the global config
		parent::__construct($config);

		// store the defined name
		if (isset($config['cookie']['cookie_name']))
		{
			$this->name = $config['cookie']['cookie_name'];
		}
	}

    /**
     * Create a new session
     *
     * @return bool  result of the create operation
     *
	 * @since  2.0.0
     */
    public function create()
    {
		// start the session
		if ( ! $this->started)
		{
			return $this->start();
		}

		// session already started
		return false;
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
		// mark the session as started
		$this->started = true;

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
		if ($this->started)
		{
			// fetch the session data (is in the session cookie too)
			if ($session = $this->findSessionId())
			{
				// and fetch the payload
				$payload = $this->decrypt($session);

				// make sure we got something meaningful
				if (is_string($payload) and substr($payload,0,2) == 'a:')
				{
					// unserialize it
					$payload = unserialize($payload);

					// verify and process the payload
					return $this->processPayload($payload);
				}
			}
		}

		// no session started, or no valid session data present
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
		// not implemented in the cookie driver, flush the data through a stop/start
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
		if ( ! $this->started)
		{
			return false;
		}

		// construct the payload
		$payload = $this->encrypt(serialize($this->assemblePayload()));

		// make sure it's within cookie specs
		if (strlen($payload) > 4096)
		{
			throw new \RuntimeException('The payload of the session cookie exceeds the maximum size of 4Kb. Use a different storage driver or reduce the size of your session.');
		}

		// mark the session as stopped
		$this->started = false;

		// and set the session cookie
		return $this->setCookie(
			$this->name,
			$payload
		);
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
		// we need to have a session started
		if ($this->started)
		{
			// mark the session as stopped
			$this->started = false;

			// reset the session containers
			$this->manager->reset();

			// delete the session cookie
			return $this->deleteCookie($this->name);
		}

		// session was not started
		return false;
	}

	/**
	 * Encrypts a string using the crypt_key configured in the config
	 *
	 * @param   string    string to be encrypted
	 *
	 * @throws  BadMethodCallException  when the required mcrypt extension is not installed
	 *
	 * @return  encrypted string
	 *
	 * @since  2.0.0
	 */
	protected function encrypt($string)
	{
		// only if we want the cookie to be encrypted
		if ($this->config['cookie']['encrypt_cookie'])
		{
			// we require the mcrypt PECL extension for this
			if ( ! function_exists('mcrypt_encrypt'))
			{
				throw new \BadMethodCallException('The Session Cookie driver requires the PHP mcrypt extension to be installed.');
			}

			// create the encyption key
			$key = hash('SHA256', $this->config['cookie']['crypt_key'], true);

			// create the IV
			$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);
			if (strlen($iv_base64 = rtrim(base64_encode($iv), '=')) != 22)
			{
				// invalid IV
				return false;
			}

			// construct the encrypted payload
			$string = $iv_base64.base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $string.md5($string), MCRYPT_MODE_CBC, $iv));
		}

		return $string;
	}

	/**
	 * Decrypts a string using the crypt_key configured in the config
	 *
	 * @param   string    string to be decrypted
	 *
	 * @throws  BadMethodCallException  when the required mcrypt extension is not installed
	 *
	 * @return  decrypted string
	 *
	 * @since  2.0.0
	 */
	protected function decrypt($string)
	{
		// only if we want the cookie to be encrypted
		if ($this->config['cookie']['encrypt_cookie'])
		{
			// we require the mcrypt PECL extension for this
			if ( ! function_exists('mcrypt_decrypt'))
			{
				throw new \BadMethodCallException('The Session Cookie driver requires the PHP mcrypt extension to be installed.');
			}

			// create the encyption key
			$key = hash('SHA256', $this->config['cookie']['crypt_key'], true);

			// key the IV from the payload
			$iv = base64_decode(substr($string, 0, 22) . '==');
			$string = substr($string, 22);

			// decrypt the payload
			$string = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, base64_decode($string), MCRYPT_MODE_CBC, $iv), "\0\4");

			// split the hash from the payload
			$hash = substr($string, -32);
			$string = substr($string, 0, -32);

			// double-check it wasn't tampered with
			if (md5($string) != $hash)
			{
				return false;
			}
		}

		return $string;
	}
}
