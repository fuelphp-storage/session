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

use Fuel\Dependency\ServiceProvider;

use Fuel\Session\Driver\Native;

/**
 * ServicesProvider class
 *
 * Defines the services published by this namespace to the DiC
 *
 * @package  Fuel\Session
 *
 * @since  1.0.0
 */
class ServicesProvider extends ServiceProvider
{
	/**
	 * @var  array  list of service names provided by this provider
	 */
	public $provides = array('session',
		'session.native',
	);

	/**
	 * Service provider definitions
	 */
	public function provide()
	{
		// \Fuel\Session\Manager
		$this->register('session', function ($dic, Driver $driver, Array $config = array())
		{
			return new Manager($driver, $config);
		});

		// \Fuel\Session\Driver\Native
		$this->register('session.native', function ($dic, Array $config = array())
		{
			return new Native($config);
		});
	}
}
