<?php
/**
 * @package    Fuel\Session
 * @version    2.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2015 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Session\Driver;

use Fuel\Session\Driver;

/**
 * Session driver using session emulation via cookies
 *
 * NOTE: this driver is not thread-safe.
 */
class Cookie extends Driver
{
	/**
	 * @var array
	 */
	protected $defaults = [
		'cookie_name'    => 'fuelcid',
		'encrypt_cookie' => true,
		'crypt_key'      => '',
	];

	/**
	 * @var boolean
	 */
	protected $started = false;

	/**
	 * @param array $config
	 */
	public function __construct(array $config = [])
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
	 * {@inheritdoc}
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
	 * {@inheritdoc}
	 */
	public function start()
	{
		// mark the session as started
		$this->started = true;

		// and read any existing session data
		return $this->read();
	}

	/**
	 * {@inheritdoc}
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

					// restore the session id if needed
					if (isset($payload['security']['id']))
					{
						$this->sessionId = $payload['security']['id'];
					}

					// verify and process the payload
					return $this->processPayload($payload);
				}
			}
		}

		// no session started, or no valid session data present
		return false;
	}

	/**
	 * {@inheritdoc}
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
	 * {@inheritdoc}
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
	 * {@inheritdoc}
	 */
	public function gc()
	{
		return true;
	}

	/**
	 * {@inheritdoc}
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
	 * @param string $string
	 *
	 * @return string
	 *
	 * @throws \BadMethodCallException  if the required mcrypt extension is not installed
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
	 * @param string $string
	 *
	 * @return string
	 *
	 * @throws \BadMethodCallException  if the required mcrypt extension is not installed
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
