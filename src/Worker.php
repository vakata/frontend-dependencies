<?php

namespace vakata\frontenddependencies;

use Composer\Composer;

class Worker
{
    protected $composer;
    protected $messager;
    protected $settings;

    public function __construct(Composer $composer, callable $messager = null)
    {
        $this->composer = $composer;
        $this->messager = $messager ?? function (string $message) { };
        $this->settings = array_merge(
            [
                // should the script always perform a clean install (fetch all packages anew)
                'clean' => false,
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
    }
    public function execute(string $reason = 'command')
    {
        // skip running if no dependencies specified
        if (!count($this->settings['dependencies'])) {
            $this->message('Frontend dependencies: No dependencies to install');
            return;
        }
        // skip running as defined in config
        if ($reason === 'install' && !$this->settings['install']) {
            return;
        }
        if ($reason === 'update' && !$this->settings['update']) {
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
        
        // install dependencies
        $this->message('Frontend dependencies: Installing ' . count($this->settings['dependencies']) . ' dependencies');
        $mode = is_file($this->settings['source'] . '/package-lock.json') && is_dir($this->settings['source'] . '/node_modules') ? 'update' : 'install'; 
        $command = 'npm ' . $mode . ' --no-optional --production --prefix ' . escapeshellarg($this->settings['source']);
        $this->message(' ' . $command);
        passthru(
            $this->composer->getConfig()->get('bin-dir') . '/' . $command
        );

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
    }

    protected function message(string $message)
    {
        return \call_user_func($this->messager, $message);
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