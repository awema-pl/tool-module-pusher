<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Rollerworks\Component\Version\Version;

class Pusher extends Command
{
    const AUTHOR_NAME = 'Karol Golec';
    const AUTHOR_EMAIL = 'developer@awema.pl';
    const NEXT_VERSION_MAJOR = false;
    const NEXT_VERSION_MINOR = false;
    const NEXT_VERSION_PATCH = true;

    protected $signature = 'push {--path=}';

    protected $onlyModules = [];

    protected $commit;

    public function handle()
    {
        $this->setCommit();
        $this->checkOptions();
        foreach ($this->getModules() as $module) {
            $this->setConfig($module);
            $this->gitAdd($module);
            $commited = $this->gitCommit($module);
            if ($commited) {
                $pushed = $this->gitPush($module);
                if ($pushed){
                    $this->gitTag($module);
                    $this->gitPushTags($module);
                }
            }

        }
    }

    /**
     * Set config
     *
     * @param string $module
     */
    private function setConfig(string $module)
    {
        $path = $this->pathModule($module);
        $process = new Process([
            'git',
            'config',
            'user.name',
            self::AUTHOR_NAME
        ], $path);
        $process->mustRun();
        $process = new Process([
            'git',
            'config',
            'user.email',
            self::AUTHOR_EMAIL
        ], $path);
        $process->mustRun();
    }

    /**
     * Path module
     *
     * @param string $module
     * @return false|string
     */
    private function pathModule(string $module)
    {
        return realpath($this->option('path') . '/' . $module);
    }

    private function checkOptions()
    {
        if (!$this->option('path')) {
            $this->error('Not set option path.');
            dd();
        }
    }

    /**
     * Git add
     *
     * @param string $module
     * @return bool
     */
    private function gitAdd(string $module)
    {
        $path = $this->pathModule($module);
        $process = new Process([
            'git',
            'add',
            '.'
        ], $path);
        $process->mustRun();
        return !!$process->getOutput();
    }

    /**
     * Git commit
     *
     * @param string $module
     * @return bool
     */
    private function gitCommit(string $module)
    {
        $path = $this->pathModule($module);
        $commit =  $this->commit ?: "Module $module";
        $process = new Process([
            'git',
            'commit',
            '-m',
           $this->commit,
        ], $path);
        $process->run();
        $output = $process->getOutput();
        return !Str::contains($output, 'nothing to commit, working tree clean');
    }

    /**
     * Git push
     *
     * @param string $module
     * @return bool
     */
    private function gitPush(string $module)
    {
        $this->info("Git push for module $module.");
        $path = $this->pathModule($module);
        $process = new Process([
            'git',
            'push'
        ], $path);
        $process->mustRun();
        $output = $process->getOutput();
        return !Str::contains($output, 'Everything up-to-date');
    }

    /**
     * Get tag next version
     *
     * @param $module
     * @return string
     */
    private function getTagNextVersion($module)
    {
        $currentTag = $this->currentTag($module);
        if (!$currentTag){
            return 'v1.0.0';
        }
        $version = Version::fromString($currentTag);

        if (self::NEXT_VERSION_MAJOR){
            $nextVersion = (string) $version->getNextIncreaseOf('major');
        } else if (self::NEXT_VERSION_MINOR){
            $nextVersion = (string)$version->getNextIncreaseOf('minor');
        } else if (self::NEXT_VERSION_PATCH){
            $nextVersion =(string) $version->getNextIncreaseOf('patch');
        } else {
            $this->error('Not choose increase method.');
            dd();
        }
        return 'v' . $nextVersion;
    }

    /**
     * Current tag
     *
     * @param $module
     * @return string
     */
    private function currentTag($module)
    {
        $path = $this->pathModule($module);
        $process = Process::fromShellCommandline('git tag | sort -V | tail -1', $path);
        $process->mustRun();
       return trim($process->getOutput());
    }

    /**
     * Git tag
     *
     * @param string $module
     */
    private function gitTag(string $module)
    {
       $nextTag =  $this->getTagNextVersion($module);
       $this->info("Git tag $nextTag for module $module.");
        $path = $this->pathModule($module);
        $process = new Process([
            'git',
            'tag',
            $nextTag,
        ], $path);
        $process->mustRun();
    }

    /**
     * Set commit
     */
    private function setCommit()
    {
        $commit = $this->ask('What is the name of the commit?', '');
        $message = $commit ? "Want to set the shift name to $commit?" : "Want to set the shift name to Module [name_module]?";
        if ($this->confirm($message)) {
            $this->commit = $commit;
        } else {
            dd();
        }
    }

    /**
     * Git push tags
     *
     * @param string $module
     */
    private function gitPushTags(string $module)
    {
        $path = $this->pathModule($module);
        $process = Process::fromShellCommandline('git push --tags', $path);
        $process->mustRun();
    }

    /**
     * Get modules
     *
     * @return array
     */
    private function getModules()
    {
        if ($this->onlyModules){
            return $this->onlyModules;
        }

        $path = $this->option('path');
        $dirs = File::directories($path);
        $modules = [];
        foreach ($dirs as $dir){
            array_push($modules, basename($dir));
        }
       return $modules;
    }
}
