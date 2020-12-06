<?php

namespace vakata\frontenddependencies;

use Composer\Composer;
use Composer\Script\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public const CMD_NAME = 'frontend-dependencies';
    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => [ [ 'worker', 0 ] ],
            ScriptEvents::POST_UPDATE_CMD => [ [ 'worker', 0 ] ],
            static::CMD_NAME => [ [ 'worker', 0 ] ]
        ];
    }
    public function worker(Event $event)
    {
        (new Worker($this->composer, $this->io))->execute($event);
    }
    public function deactivate(Composer $composer, IOInterface $io)
	{
	}


	public function uninstall(Composer $composer, IOInterface $io)
	{
	}
}

