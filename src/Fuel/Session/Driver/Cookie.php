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
use Fuel\Session\DataContainer;
use Fuel\Session\FlashContainer;

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
			$this->setName($config['cookie']['cookie_name']);
		}
	}

    /**
     * Create a new session
     *
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the start operation
	 * @since  2.0.0
     */
    public function create(DataContainer $data, FlashContainer $flash)
    {
		// start the session
		if ( ! $this->started)
		{
			$this->start($data, $flash);
		}
	}

    /**
     * Start the session, and read existing session data back
     *
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the start operation
	 * @since  2.0.0
     */
    public function start(DataContainer $data, FlashContainer $flash)
    {
		// generate a new session id
		$this->regenerate();

		// mark the session as started
		$this->started = true;

		// and read any existing session data
		return $this->read($data, $flash);
	}

    /**
     * Read session data
     *
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the read operation
	 * @since  2.0.0
     */
    public function read(DataContainer $data, FlashContainer $flash)
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
					return $this->processPayload($payload, $data, $flash);
				}
			}
		}

		// no session started, or no valid session data present
		return false;
	}

    /**
     * Write session data
     *
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the write operation
	 * @since  2.0.0
     */
    public function write(DataContainer $data, FlashContainer $flash)
    {
		// not implemented in the cookie driver, flush the data through a stop/start
		$this->stop($data, $flash);
		$this->start($data, $flash);
	}

    /**
     * Stop the session
     *
     * @param  DataContainer $data
     * @param  FlashContainer $flash
     *
     * @return bool  result of the write operation
	 * @since  2.0.0
     */
    public function stop(DataContainer $data, FlashContainer $flash)
    {
		// bail out if we don't have an active session
		if ( ! $this->started)
		{
			return false;
		}
		$payload = $this->encrypt(serialize($this->assemblePayload($data, $flash)));

		if (strlen($payload) > 4096)
		{
			throw new \RuntimeException('The payload of the session cookie exceeds the maximum size of 4Kb. Use a different storage driver or reduce the size of your session.');
		}

		return $this->setCookie(
			$this->name,
			$payload
		);
	}

    /**
     * Destroy the session
     *
     * @return bool  result of the write operation
	 * @since  2.0.0
     */
    public function destroy()
    {
		// we need to have a session started
		if ($this->started)
		{
			return $this->deleteCookie($this->name);
		}

		return false;
	}

	/**
	 * Encrypts a string using the crypt_key configured in the config
	 *
	 * @param   string    string to be encrypted
	 * @return  encrypted string
	 */
	protected function encrypt($string)
	{
		if ($this->config['cookie']['encrypt_cookie'])
		{
			if ( ! function_exists('mcrypt_encrypt'))
			{
				throw new \Exception('The Session Cookie driver requires the PHP mcrypt extension to work securely.');
			}

			// create the encyption key
			$key = hash('SHA256', $this->config['cookie']['crypt_key'], true);

			// create the IV
			$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);
			if (strlen($iv_base64 = rtrim(base64_encode($iv), '=')) != 22)
			{
				return false;
			}

			// return the encrypted payload
			$string = $iv_base64.base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $string.md5($string), MCRYPT_MODE_CBC, $iv));
		}

		return $string;
	}

	/**
	 * Decrypts a string using the crypt_key configured in the config
	 *
	 * @param   string    string to be decrypted
	 * @return  decrypted string
	 */
	protected function decrypt($string)
	{
		if ($this->config['cookie']['encrypt_cookie'])
		{
			if ( ! function_exists('mcrypt_decrypt'))
			{
				throw new \Exception('The Session Cookie driver requires the PHP mcrypt extension to work securely.');
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

			// make sure it wasn't tampered with
			if (md5($string) != $hash)
			{
				return false;
			}
		}

		return $string;
	}
}
