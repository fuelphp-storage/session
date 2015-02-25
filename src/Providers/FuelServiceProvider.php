<?php
/**
 * @package    Fuel\Session
 * @version    2.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2015 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Session\Providers;

use Fuel\Session\Driver;
use League\Container\ServiceProvider;


/**
 * FuelPHP ServiceProvider class for this package
 *
 * @package Fuel\Session
 *
 * @since 1.0
 */
class FuelServiceProvider extends ServiceProvider
{
	/**
	 * @var array
	 */
	protected $provides = [
		'session',
		'session.native',
		'session.cookie',
		'session.file',
	];

	/**
	 * {@inheritdoc}
	 */
	public function register()
	{
		// \Fuel\Session\Manager
		$this->register('session', function ($dic, $config = array())
		{
			// get the session config
			$stack = $this->container->resolve('requeststack');
			if ($request = $stack->top())
			{
				$component = $request->getComponent();
			}
			else
			{
				$component = $this->container->resolve('application::__main')->getRootComponent();
			}

			// check if only a driver name or object is passed
			if ( ! is_array($config))
			{
				$config = array('driver' => $config);
			}

			$config = \Arr::merge($component->getConfig()->load('session', true), $config);

			// determine the driver to load
			if ($config['driver'] instanceOf Driver)
			{
				$driver = $config['driver'];
			}
			elseif (strpos('\\', $config['driver']) !== false and class_exists($config['driver']))
			{
				$class = $config['driver'];
				$driver = new $class($config);
			}
			else
			{
				$driver = $dic->resolve('session.'.strtolower($config['driver']), array($config));
			}

			$manager = $dic->resolve('Fuel\Session\Manager', array($driver, $config, $component->getApplication()));

			// start the session
			$manager->start();

			$event = $component->getApplication()->getEvent();

			// and use the applications' event instance make sure it ends too
			$event->addListener('shutdown', function($event) use ($manager) {
				$manager->stop();
			});

			// return the instance
			return $manager;
		});

		// \Fuel\Session\Driver\Cookie
		$this->register('session.cookie', function ($dic, Array $config = array())
		{
			return $dic->resolve('Fuel\Session\Driver\Cookie', array($config));
		});

		// \Fuel\Session\Driver\Native
		$this->register('session.native', function ($dic, Array $config = array())
		{
			return $dic->resolve('Fuel\Session\Driver\Native', array($config));
		});

		// \Fuel\Session\Driver\File
		$this->register('session.file', function ($dic, Array $config = array())
		{
			return $dic->resolve('Fuel\Session\Driver\File', array($config));
		});
	}
}
