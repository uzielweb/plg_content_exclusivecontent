<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Exclusivecontent
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Content\Exclusivecontent\Extension\Exclusivecontent;

return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$plugin = new Exclusivecontent(
					$container->get(DispatcherInterface::class),
					(array) PluginHelper::getPlugin('content', 'exclusivecontent')
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};