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

class Plugin implements PluginInterface, EventSubscriberInterface, CommandProvider, Capable
{
    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }
    public function getCommands()
    {
        return [
            new Command()
        ];
    }
    public function getCapabilities()
    {
        return [
            'Composer\Plugin\Capability\CommandProvider' => 'vakata\frontenddependencies\Plugin'
        ];
    }
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => [ [ 'onPostInstall', 0 ] ],
            ScriptEvents::POST_UPDATE_CMD => [ [ 'onPostUpdate', 0 ] ]
        ];
    }

    public function onPostInstall(Event $event)
    {
        (new Worker(
            $event->getComposer(),
            function (string $message) {
                $this->io->write($message);
            }
        ))->execute("install");
    }
    public function onPostUpdate(Event $event)
    {
        (new Worker(
            $event->getComposer(),
            function (string $message) {
                $this->io->write($message);
            }
        ))->execute("update");
    }
}

