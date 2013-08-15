<?php
/**
 * @package    Fuel\Session
 * @version    2.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2013 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Session\Providers;

use Fuel\Session\Driver;

use Fuel\Dependency\ServiceProvider;


/**
 * FuelPHP ServiceProvider class for this package
 *
 * @package  Fuel\Session
 *
 * @since  1.0.0
 */
class FuelServiceProvider extends ServiceProvider
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
		$this->register('session', function ($dic, Array $config = array())
		{
			// get the session config
			$stack = $this->container->resolve('requeststack');
			if ($request = $stack->top())
			{
				$instance = $request->getApplication()->getConfig();
			}
			else
			{
				$instance = $this->container->resolve('application.main')->getConfig();
			}
			$config = \Arr::merge($instance->load('session', true), $config);

			// check if we have a driver configured
			if (empty($config['driver']))
			{
				throw new \RuntimeException('No session driver given or no default session driver set.');
			}

			// determine the driver to load
			if ($config['driver'] instanceOf Driver)
			{
				$driver = $config['driver'];
			}
			elseif (class_exists($config['driver']))
			{
				$class = $config['driver'];
				$driver = new $class($config);
			}
			else
			{
				$driver = $dic->resolve('session.'.$config['driver'], array($config));
			}

			return $dic->resolve('Fuel\Session\Manager', array($driver, $config));
		});

		// \Fuel\Session\Driver\Native
		$this->register('session.native', function ($dic, Array $config = array())
		{
			return $dic->resolve('Fuel\Session\Driver\Native', array($config));
		});
	}
}
