<?php

namespace vakata\frontenddependencies;

use Composer\Composer;
use Composer\Script\Event;
use Composer\IO\IOInterface;

class Worker
{
    protected $composer;
    protected $io;
    protected $settings;

    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->settings = array_merge(
            [
                // should the script always perform a clean install (fetch all packages anew)
                'clean' => true,
                // should the script run after composer install
                'install' => true,
                // should the script run after composer update
                'update' => true,
                // the path relative to the project root where dependencies will be stored
                'target' => 'assets',
                // the actual dependencies
                'dependencies' => [],
            ],
            $this->composer->getPackage()->getExtra()['vakata']['frontend-dependencies'] ?? []
        );
        $this->settings['target'] = rtrim(getcwd(), '/\\') . '/' . trim($this->settings['target'], '/\\');
        $this->settings['source'] = $this->composer->getConfig()->get('vendor-dir').'/vakata/frontend-dependencies/tmp';
        $this->settings['mouf'] = rtrim($this->composer->getPackage()->getExtra()['mouf']['nodejs']['targetDir'] ?? $this->composer->getConfig()->get('vendor-dir') . '/nodejs/nodejs', '/\\');
    }
    public function execute(Event $event)
    {
        $reason = $event->getName();
        // skip running if no dependencies specified
        if (!count($this->settings['dependencies'])) {
            $this->message('Frontend dependencies: No dependencies to install');
            return;
        }
        // skip running as defined in config
        if ($reason === 'post-install-cmd' && !$this->settings['install']) {
            return;
        }
        if ($reason === 'post-update-cmd' && !$this->settings['update']) {
            return;
        }

        // prepare internal folder
        if (!is_dir($this->settings['source'])) {
            mkdir($this->settings['source'], 0775, true);
        }
        if ($this->settings['clean']) {
            $this->empty($this->settings['source']);
        }
        file_put_contents($this->settings['source'] . '/package.json', json_encode([
            'name' => 'private',
            'description' => 'private',
            'repository' => 'private/private',
            'license' => 'UNLICENSED',
            'private' => true,
            'dependencies' => array_map(function ($v) {
                return !is_array($v) ? (string)$v : ($v['version'] ?? '*');
            }, $this->settings['dependencies'])
        ]));
        file_put_contents($this->settings['source'] . '/README', 'PRIVATE');

        $mouf = new \Mouf\NodeJsInstaller\NodeJsPlugin();
        $mouf->activate($this->composer, $this->io);
        $mouf->onPostUpdateInstall($event);

        // install dependencies
        $this->message('Frontend dependencies: Installing ' . count($this->settings['dependencies']) . ' dependencies');
        $mode = is_file($this->settings['source'] . '/package-lock.json') && is_dir($this->settings['source'] . '/node_modules') ? 'update' : 'install'; 
        $command = 'npm ' . $mode . ' --no-optional --production --prefix ' . escapeshellarg($this->settings['source']);
        $this->message(' ' . $command);

        // chdir is needed on windows
        $cwd = getcwd();
        chdir($this->settings['source']);
        passthru(
            $this->composer->getConfig()->get('bin-dir') . DIRECTORY_SEPARATOR . $command
        );
        chdir($cwd);

        // copy dependencies
        $cnt = 0;
        $this->empty($this->settings['target']);
        foreach ($this->settings['dependencies'] as $name => $details) {
            if (!is_dir($this->settings['source'] . '/node_modules/' . $name)) {
                $this->message(' ' . $name . ' is missing!');
                continue;
            }
            if (!is_dir($this->settings['target'] . '/' . $name)) {
                mkdir($this->settings['target'] . '/' . $name, 0775, true);
            }
            if (!is_array($details) || !isset($details["src"])) {
                $this->copy(
                    $this->settings['source'] . '/node_modules/' . $name,
                    $this->settings['target'] . '/' . $name
                );
            } else {
                foreach (glob($this->settings['source'] . '/node_modules/' . $name . '/' . $details["src"], GLOB_BRACE)
                    as $res
                ) {
                    $this->copy($res, $this->settings['target'] . '/' . $name . '/' . basename($res));
                }
            }
            $cnt ++;
        }
        $this->message('Frontend dependencies: Installed ' . $cnt . ' dependencies');
        $this->empty($this->settings['mouf']);
        $bin = $this->composer->getConfig()->get('bin-dir');
        foreach (["node", "npm", "node.bat", "npm.bat"] as $file) {
            $file = $bin . DIRECTORY_SEPARATOR . $file;
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    protected function message(string $message)
    {
        return $this->io->write($message);
    }
    protected function empty(string $dir, bool $self = false)
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
                    @unlink($item->getPathname());
                }
            }
            if ($self) {
                @rmdir($dir);
            }
        }
    }
    protected function copy(string $src, string $dst)
    {
        if (is_dir($src)) {
            if (!is_dir($dst)) {
                mkdir($dst, 0775);
            }
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($items as $item) {
                if ($item->isDir()) {
                    mkdir($dst . DIRECTORY_SEPARATOR . $items->getSubPathName());
                } else {
                    copy($item, $dst . DIRECTORY_SEPARATOR . $items->getSubPathName());
                }
            }
        } else {
            copy($src, $dst);
        }
    }
}