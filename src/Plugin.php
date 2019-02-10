<?php

namespace vakata\frontenddependencies;

use Composer\Composer;
use Composer\Package\AliasPackage;
use Composer\Package\CompletePackage;
use Composer\Script\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => array(
                array('onPostUpdateInstall', 0),
            ),
            ScriptEvents::POST_UPDATE_CMD => array(
                array('onPostUpdateInstall', 0),
            )
        );
    }

    public function onPostUpdateInstall(Event $event)
    {
        $cnf = $event->getComposer()->getPackage()->getExtra()['vakata']['frontend-dependencies'] ?? [];
        $bin = $event->getComposer()->getConfig()->get('bin-dir');
        $dir = $event->getComposer()->getConfig()->get('vendor-dir').'/vakata/frontend-dependencies/';
        $cnf = array_merge([ 'reset' => false, 'dependencies' => [], 'target' => 'assets' ], $cnf);
        $cur = \getcwd();
        $cnf['target'] = rtrim($cur, '/\\') . '/' . trim($cnf['target'], '/\\');

        if ($cnf['reset']) {
            $this->emptyDir($cnf['target']);
        }
        if (!is_dir($cnf['target'])) {
            mkdir($cnf['target'], 0777, true);
        }
        $dependencies = [];
        foreach ($cnf['dependencies'] as $name => $details) {
            $dependencies[$name] = is_string($details) ? $details : ($details['version'] ?? '*');
        }
        file_put_contents($dir . 'package.json', json_encode([
            'name' => 'private',
            'description' => 'private',
            'repository' => 'private/private',
            'license' => 'UNLICENSED',
            'private' => true,
            'dependencies' => $dependencies
        ]));
        file_put_contents($dir . 'README', 'PRIVATE');

        chdir($dir);
        $this->io->write($bin . '/npm '.(is_file($dir . 'package-lock.json') && is_dir($dir . 'node_modules') ? 'update' : 'install').' --no-optional --production');
        passthru($bin . '/npm '.(is_file($dir . 'package-lock.json') && is_dir($dir . 'node_modules') ? 'update' : 'install').' --no-optional --production');
        
        $tasks = [];
        chdir($dir . "node_modules");
        foreach ($cnf['dependencies'] as $name => $details) {
            $tasks[$name] = [];
            if (!is_dir($name)) {
                continue;
            }
            if (!is_array($details) || !isset($details["src"])) {
                $tasks[$name][$name] = $cnf['target'] . '/' . $name;
            } elseif (!is_array($details['src']) && is_dir($name . '/' . ltrim($details['src']))) {
                $tasks[$name][$name . '/' . ltrim($details['src'], '/')] = $cnf['target'] . '/' . $name;
            } else {
                if (!is_array($details['src'])) {
                    $details['src'] = [$details['src']];
                }
                foreach ($details['src'] as $path) {
                    $tasks[$name][$name . '/' . ltrim($path, '/\\')] = $cnf['target'] . '/' . $name . '/' . basename($path);
                }
            }
        }
        if (!is_dir($cnf['target'])) {
            mkdir($cnf['target'], 0775, true);
        }
        $this->emptyDir($cnf['target']);
        foreach ($tasks as $dependency => $files) {
            if (!is_dir($cnf['target'] . '/' . $dependency)) {
                mkdir($cnf['target'] . '/' . $dependency, 0775, true);
            }
            foreach ($files as $old => $new) {
                if (is_dir($old)) {
                    if (!is_dir($new)) {
                        mkdir($new, 0775);
                    }
                    $items = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($old, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($items as $item) {
                        if ($item->isDir()) {
                            mkdir($new . DIRECTORY_SEPARATOR . $items->getSubPathName());
                        } else {
                            copy($item, $new . DIRECTORY_SEPARATOR . $items->getSubPathName());
                        }
                    }
                } else {
                    copy($old, $new);
                }
            }
        }
        chdir($cur);
    }
    
    protected function emptyDir(string $dir, bool $self = false)
    {
        if (is_dir($dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $item) {
                if ($item->isDir() && !$item->isLink()) {
                    @rmdir($item->getRealPath());
                } else {
                    @unlink($item->getRealPath());
                }
            }
            if ($self) {
                @rmdir($dir);
            }
        }
    }
}
