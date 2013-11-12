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
 * Session driver using a file backend
 *
 * NOTE: this driver is not thread-safe.
 *
 * @package  Fuel\Session
 *
 * @since  2.0.0
 */
class File extends Driver
{
	/**
	 * @var  array  session driver config defaults
	 */
	protected $defaults = array(
		'cookie_name'       => 'fuelfid',
		'path'              => '/tmp',
		'gc_probability'    => 5
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
		$config['file'] = array_merge($this->defaults, isset($config['file']) ? $config['file'] : array());

		// validate the path
		if ( ! $path = realpath($config['file']['path']))
		{
			throw new \Exception('Configured session storage directory\ "'.$this->config['file']['path']." is not accessable.");
		}
		$config['file']['path'] = $path.DIRECTORY_SEPARATOR;

		// call the parent to process the global config
		parent::__construct($config);

		// store the defined name
		if (isset($config['file']['cookie_name']))
		{
			$this->name = $config['file']['cookie_name'];
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
		// create the session
		if ( ! $this->started)
		{
			// generate a new session id
			$this->regenerate();

			// create a new session file, and check the result
			if ($this->writeFile(serialize($this->assemblePayload())))
			{
				// mark the session as started
				$this->started = true;

				return true;
			}
		}

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
	 * @return bool  result of the write operation
	 *
	 * @since  2.0.0
	 */
	public function read()
	{
		// bail out if we don't have an active session
		if ( ! $this->started)
		{
			return false;
		}

		// fetch the session id
		if ($sessionId = $this->findSessionId())
		{
			// fetch the payload
			if ($payload = $this->readFile())
			{
				// unserialize it, verify and process the payload
				return $this->processPayload(unserialize($payload));
			}
		}

		// no session found, reset the started flag
		$this->started = false;

		// and create a new session
		return $this->create();
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
		// bail out if we don't have an active session
		if ( ! $this->started)
		{
			return false;
		}

		// assemble the session payload, store it, return the result
		return $this->writeFile(serialize($this->assemblePayload()));
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

		// write the data in the session
		$this->write();

		// mark the session as stopped
		$this->started = false;

		// set the session cookie
		return $this->setCookie(
			$this->name,
			$this->sessionId
		);

		// do some garbage collection
		if (mt_rand(0,100) < $this->config['file']['gc_probability'])
		{
			// delete expired session files
			$expired = time() - $this->expiration;

			// loop over the files in the session folder
			if ($handle = opendir($this->config['file']['path']))
			{
				while (($file = readdir($handle)) !== false)
				{
					if (filetype($this->config['file']['path'] . $file) == 'file' and
						strpos($file, $this->config['file']['cookie_name'].'_') === 0 and
						filemtime($this->config['file']['path'] . $file) < $expire)
					{
						unlink($this->config['file']['path'] . $file);
					}
				}
				closedir($handle);
			}
		}
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

			// construct the session file name
			$file = $this->config['file']['path'].$this->config['file']['cookie_name'].'_'.$this->sessionId;

			// delete the session file
			if (is_file($file))
			{
				unlink($file);
			}

			// delete the session cookie
			return $this->deleteCookie($this->name);
		}

		// session was not started
		return false;
	}

	/**
	 * Writes the session file
	 *
	 * @return  boolean, true if the write was successful, false if not
	 */
	protected function writeFile($payload)
	{
		// construct the session file name
		$file = $this->config['file']['path'].$this->config['file']['cookie_name'].'_'.$this->sessionId;

		// open the file
		if ( ! $handle = fopen($file,'c'))
		{
			throw new \Exception('Could not open the session file in "'.$this->config['file']['path']." for write access");
		}

		// wait for a lock
		while ( ! flock($handle, LOCK_EX));

		// erase existing contents
		ftruncate($handle, 0);

		// write the session data
		fwrite($handle, $payload);

		//release the lock
		flock($handle, LOCK_UN);

		// close the file
		fclose($handle);

		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Reads the session file
	 *
	 * @return  mixed, the payload if the file exists, or false if not
	 */
	protected function readFile()
	{
		// construct the session file name
		$file = $this->config['file']['path'].$this->config['file']['cookie_name'].'_'.$this->sessionId;

		if (is_file($file) and $handle = fopen($file,'r'))
		{
			// wait for a lock
			while ( ! flock($handle, LOCK_SH));

			if ($size = filesize($file))
			{
				// read the session data
				$payload = fread($handle, $size);
			}
			else
			{
				// file exists, but empty?
				$payload = false;
			}

			//release the lock
			flock($handle, LOCK_UN);

			// close the file
			fclose($handle);

			// return the loaded payload
			return $payload;
		}

		// session file did not exist or could not be opened
		return false;
	}
}
