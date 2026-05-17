<?php

declare( strict_types=1 );

namespace Render\Autoloader;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

use Render\Autoloader\Attributes\AutoRunPlugin;
use Render\Autoloader\Directories\AutoloadPlugin as DirectoryAutoloadPlugin;

class Plugin implements PluginInterface, EventSubscriberInterface
{
	private Composer $composer;
	private IOInterface $io;

	/**
	 * Called when plugin is activated.
	 *
	 * @param Composer    $composer Composer instance
	 * @param IOInterface $io       IO for console output
	 *
	 * @return void
	 */
	public function activate ( Composer $composer, IOInterface $io ): void
	{
		$this->composer = $composer;
		$this->io       = $io;
	}

	/**
	 * Called when plugin is deactivated.
	 *
	 * @param Composer    $composer Composer instance
	 * @param IOInterface $io       IO for console output
	 *
	 * @return void
	 */
	public function deactivate ( Composer $composer, IOInterface $io ): void
	{
	}

	/**
	 * Called when plugin is uninstalled.
	 *
	 * @param Composer    $composer Composer instance
	 * @param IOInterface $io       IO for console output
	 *
	 * @return void
	 */
	public function uninstall ( Composer $composer, IOInterface $io ): void
	{
	}

	/**
	 * Get Composer events this plugin subscribes to.
	 *
	 * @return array<string, string> Event name => method name
	 */
	public static function getSubscribedEvents (): array
	{
		return [ ScriptEvents::PRE_AUTOLOAD_DUMP => 'onPreAutoloadDump' ];
	}

	/**
	 * Handle PRE_AUTOLOAD_DUMP event.
	 *
	 * @param Event $event Composer script event
	 *
	 * @return void
	 */
	public function onPreAutoloadDump ( Event $event ): void
	{
		$extra     = $this->composer->getPackage()->getExtra();
		$vendorDir = $this->composer->getConfig()->get( 'vendor-dir' );

		if ( !empty( $loadFile = ( new AutoRunPlugin( $this->io, $vendorDir ) )->run( $extra ) ) ) {
			$this->registerAutoloadFile( $loadFile );
		}

		if ( !empty( $loadFile = ( new DirectoryAutoloadPlugin( $this->io, $vendorDir ) )->run( $extra ) ) ) {
			$this->registerAutoloadFile( $loadFile );
		}
	}

	/**
	 * Register generated file with Composer autoloader.
	 *
	 * Adds to autoload.files array so it runs on require vendor/autoload.php.
	 *
	 * @param string $file Path to generated bootstrap file
	 *
	 * @return void
	 */
	private function registerAutoloadFile ( string $file ): void
	{
		$package  = $this->composer->getPackage();
		$autoload = $package->getAutoload();

		$autoload['files'] ??= [];

		if ( !in_array( $file, $autoload['files'], TRUE ) ) {
			$autoload['files'][] = $file;
		}

		$package->setAutoload( $autoload );
	}


}
