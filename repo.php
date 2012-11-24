#!/usr/bin/env php
<?php

/**
 * Simple tool to manage multiple repositories.
 * Maintains distribution index with latest revisions for each subproject
 * for faster updates.
 *
 * Repo commands:
 *
 * repo help
 * repo install <distname> [<method>] [<destdir>]
 * repo update
 * repo check
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

declare(ticks = 1);

function sigexit()
{
    exit;
}
pcntl_signal(SIGINT, 'sigexit');
pcntl_signal(SIGTERM, 'sigexit');

Repo::run($argv);
exit;

class Repo
{
    var $cfg_dir;
    var $prefixes_file, $prefixes = array();
    var $dist_name, $dist = array();
    var $localindex_file, $localindex = array('params' => array(), 'revs' => array());
    var $distindex_file, $distindex = array();
    var $method;
    var $dest_dir;

    static $scriptName;

    /**
     * Console entry point
     */
    static function run($argv)
    {
        $cmd = '';
        $dist = '';
        $destdir = '';
        $method = '';
        self::$scriptName = array_shift($argv);

        for ($i = 0; $i < count($argv); $i++)
        {
            $arg = $argv[$i];
            if ($arg === '--help' || $arg === '-h' || $arg === 'help')
            {
                $cmd = 'help';
                break;
            }
            elseif (!$cmd)
            {
                $cmd = $arg;
            }
            elseif (!$dist)
            {
                $dist = $arg;
            }
            elseif (!$method)
            {
                $method = $arg;
            }
            elseif (!$destdir)
            {
                $destdir = $arg;
            }
        }

        if (!$cmd || $cmd === 'help')
        {
            self::printHelp();
        }
        elseif ($cmd === 'install' || $cmd === 'check' || $cmd === 'update')
        {
            $repo = new Repo($dist, $method, $destdir);
            $repo->$cmd();
        }
        else
        {
            print "Unknown command: $cmd\n";
            exit(9);
        }
    }

    /**
     * Help printer
     */
    static function printHelp()
    {
        $s = self::$scriptName;
        $dir = dirname(__FILE__);
        print "Simple script to manage multiple repositories
Maintains distribution index with latest revisions for each subproject
for faster updates.

USAGE:

php $s install <distname> [<method>] [<dest_dir>]
    Install distribution <distname> using <method> (default 'ro').
    Optionally set destination to <dest_dir> (relative to $dir).

php $s update
    Fast update: refresh configuration from repository, then update modules of
    last installed distribution, for which revision in the local index differs
    from revision in the distribution index.

php $s check
    Slow update: update ALL modules of last installed distribution.

Supported revision control systems (vcs/method):
    git/ro: fast readonly clones without full history (for installation)
    git/rw: slow read-write clones with full history (for development)
";
    }

    /**
     * Constructor
     */
    function __construct($dist_name, $method = false, $destdir = false)
    {
        $this->dist_name = $dist_name;
        $this->method = $method ?: 'ro';
        $this->cfg_dir = dirname(__FILE__);
        $this->dest_dir = $destdir;
        $this->parse_localindex();
        if (!$this->dist_name)
        {
            print "Distribution not specified, exiting\n";
            exit(1);
        }
        $this->prefixes_file = $this->cfg_dir.'/prefixes.ini';
        $this->dist_cfg = $this->cfg_dir."/{$this->dist_name}.ini";
    }

    /**
     * Destructor - writes local index and distribution index
     */
    function __destruct()
    {
        $this->localindex['params'] = array(
            'dest_dir' => $this->dest_dir,
            'method' => $this->method,
            'dist' => $this->dist_name,
        );
        write_ini_file($this->localindex_file, $this->localindex);
        if ($this->distindex)
        {
            write_ini_file($this->distindex_file, $this->distindex);
        }
    }

    /**
     * Parse distribution config INI file
     */
    function parse_config($cfg = false)
    {
        if (!$cfg)
        {
            $cfg = $this->dist_cfg;
        }
        $dist = parse_ini_file($cfg, true);
        if (!$dist)
        {
            print "$cfg is corrupt or does not exist, exiting\n";
            exit(2);
        }
        if (isset($dist['_params']))
        {
            if (isset($dist['_params']['include']))
            {
                foreach ((array)$dist['_params']['include'] as $file)
                {
                    $this->parse_config($this->cfg_dir.'/'.$file);
                }
            }
            if (isset($dist['_params']['destination']) &&
                !$this->dest_dir)
            {
                $this->dest_dir = $dist['_params']['destination'];
            }
            if (isset($dist['_params']['prefixes']))
            {
                $this->prefixes_file = $this->cfg_dir.'/'.$dist['_params']['prefixes'];
            }
            unset($dist['_params']);
        }
        foreach ($dist as $path => $cfg)
        {
            if (!is_array($cfg))
            {
                print "$cfg is corrupt, exiting\n";
                exit(2);
            }
            $this->dist[self::canonical_path($path)] = $cfg;
        }
    }

    /**
     * Canonicalise path - remove ././, dir/../ and etc
     */
    static function canonical_path($path)
    {
        $prevpath = preg_replace('#//*(\./+)*#s', '/', trim($path, '/'));
        $prevpath = preg_replace('#(/|^)\.(?![^/])#s', '', $prevpath);
        while (($path = preg_replace('#[^/]+/\.\.(?![^/])#s', '', $prevpath)) != $prevpath)
        {
            $prevpath = $path;
        }
        if ($path === '')
        {
            $path = '.';
        }
        return $path;
    }

    /**
     * Parse local index (should NOT be versioned)
     */
    function parse_localindex()
    {
        $this->localindex_file = $this->cfg_dir."/.localindex";
        $this->localindex = @parse_ini_file($this->localindex_file, true);
        if (!$this->dist_name &&
            !empty($this->localindex['params']['dist']))
        {
            $this->dist_name = $this->localindex['params']['dist'];
        }
        if (!$this->dest_dir &&
            !empty($this->localindex['params']['dest_dir']))
        {
            $this->dest_dir = $this->localindex['params']['dest_dir'];
        }
    }

    /**
     * Parse distribution index (should be versioned)
     */
    function parse_distindex()
    {
        $this->distindex_file = $this->cfg_dir."/{$this->dist_name}-index.ini";
        if (!file_exists($this->distindex_file))
        {
            print "Distribution index for {$this->dist_name} does not exist, will be created\n";
        }
        else
        {
            $this->distindex = parse_ini_file($this->distindex_file, true) ?: array();
            if (!$this->distindex)
            {
                print "Warning: Distribution index for {$this->dist_name} is corrupt, will be recreated\n";
            }
        }
    }

    /**
     * Parse prefixes INI file
     */
    function parse_prefixes()
    {
        if (file_exists($this->prefixes_file))
        {
            $this->prefixes = parse_ini_file($this->prefixes_file, true) ?: array();
        }
    }

    /**
     * Creates destination path and sets resulting paths for all modules
     */
    function set_paths()
    {
        if (!$this->dest_dir && ''.$this->dest_dir !== '0')
        {
            $this->dest_dir = $this->cfg_dir;
        }
        if (!file_exists($this->dest_dir))
        {
            mkdir($this->dest_dir, 0777, true);
        }
        if (!is_dir($this->dest_dir))
        {
            print "Destination directory {$this->dest_dir} is not a directory, exiting\n";
            exit(3);
        }
        $this->dest_dir = realpath($this->dest_dir);
        foreach ($this->dist as $path => &$cfg)
        {
            $cfg['path'] = $this->dest_dir.'/'.$path;
        }
    }

    /**
     * Set path revision to $rev
     * @param $path Path like in distribution index
     * @param $rev Revision
     */
    function setrev($path, $rev)
    {
        if ($rev && !isset($this->distindex[$path]) || $this->distindex[$path] !== $rev)
        {
            print "$path latest version updated to $rev\n";
            $this->distindex[$path] = $rev;
        }
        $this->localindex['revs'][$this->dist[$path]['path']] = $rev;
    }

    /**
     * Install all modules of current distribution
     */
    function install()
    {
        $this->refresh_config();
        foreach ($this->dist as $path => $cfg)
        {
            $suff = $cfg['vcs'].'_'.$this->method;
            $ok = $this->{"install_$suff"}($cfg);
            $rev = $this->{"getrev_$suff"}($cfg);
            if (!$ok && !$rev)
            {
                print "Failed to clone $path, exiting\n";
                exit(6);
            }
            $this->setrev($path, $rev);
        }
    }

    /**
     * Update and reload configs and distindex from configuration repository
     * @TODO: Support other revision control systems for distribution index
     */
    function refresh_config()
    {
        if (is_dir($this->cfg_dir.'/.git'))
        {
            chdir($this->cfg_dir);
            self::system('git pull --rebase');
            $status = self::system('git status --porcelain -uno', true);
            foreach (explode("\n", $status) as $line)
            {
                list($st, $fn) = explode(' ', trim($line));
                if (($st == 'DD' || $st{0} == 'U' || $st{1} == 'U') &&
                    $line !== $this->dist_name.'-index.ini')
                {
                    print "There are unmerged paths, please resolve conflicts before using repo\n$status";
                    exit(8);
                }
            }
        }
        $this->parse_config();
        $this->parse_prefixes();
        $this->parse_distindex();
        $this->set_paths();
        $this->rewrite_prefixes();
    }

    /**
     * Update modules of current distribution, for which revision in the
     * local index differs from revision in the distribution index, or
     * just all modules, if $force is true.
     */
    function update($force = false)
    {
        $this->refresh_config();
        $updated = false;
        foreach ($this->dist as $path => $cfg)
        {
            if ($force ||
                !isset($this->localindex['revs'][$cfg['path']]) ||
                $this->localindex['revs'][$cfg['path']] !== $this->distindex[$path])
            {
                $updated = true;
                $suff = $cfg['vcs'].'_'.$this->method;
                $this->{"update_$suff"}($cfg);
                $rev = $this->{"getrev_$suff"}($cfg);
                $this->setrev($path, $rev);
            }
        }
        if (!$updated)
        {
            print "Everything up-to-date\n";
        }
    }

    /**
     * Just an alias for forced update.
     */
    function check()
    {
        $this->update(true);
    }

    /**
     * Resolve prefixes in repository URLs based on current fetch method
     */
    function rewrite_prefixes()
    {
        foreach ($this->dist as $path => &$cfg)
        {
            if (($p = strpos($cfg['repo'], ':')) !== false)
            {
                $prefix = substr($cfg['repo'], 0, $p);
                $repo = substr($cfg['repo'], $p+1);
                if (!isset($this->prefixes[$prefix]))
                {
                    print "Prefix '$prefix' is undefined, exiting\n";
                    exit(4);
                }
                $cfg['vcs'] = $this->prefixes[$prefix]['vcs'];
                if (!isset($this->prefixes[$prefix]['methods'][$this->method]))
                {
                    print "No repository URL found for prefix '$prefix' and method '{$this->method}', exiting\n";
                    exit(5);
                }
                $cfg['repo'] = str_replace('$REPO', $repo, $this->prefixes[$prefix]['methods'][$this->method]);
            }
        }
    }

    /**
     * Similar to PHP function system(), but returns status code
     * or all output if $return_out is true, not just the last output line.
     */
    static function system($cmd, $return_out = false)
    {
        $proc = proc_open($cmd, array(STDIN, $return_out ? array('pipe', 'w') : STDOUT, STDERR), $pipes);
        $st = proc_get_status($proc);
        $pid = $st['pid'];
        $status = 0;
        if ($return_out)
        {
            $contents = stream_get_contents($pipes[1]);
        }
        if ($pid)
        {
            while (pcntl_waitpid($pid, $status) != $pid) {}
            $status = pcntl_wexitstatus($status);
        }
        proc_close($proc);
        if ($return_out)
        {
            return $status ? false : $contents;
        }
        return $status;
    }

    /**
     * Version control system support functions.
     * For each VCS+method, three functions must be defined:
     *
     * install_<vcs>_<method>($cfg):
     *     Initially install a module specified by $cfg.
     * update_<vcs>_<method>($cfg):
     *     Update a module.
     * getrev_<vcs>_<method>($cfg):
     *     Return current revision of an installed module.
     */

    /**
     * Shallow git checkout (2 last commits, no full history)
     */

    function install_git_ro($cfg)
    {
        $branch = !empty($cfg['branch']) ? $cfg['branch'] : 'master';
        $dest = $cfg['path'];
        $repo = $cfg['repo'];
        return !self::system("git clone --depth 1 --single-branch --branch \"$branch\" \"$repo\" \"$dest\"");
    }

    function update_git_ro($cfg)
    {
        $dest = $cfg['path'];
        chdir($dest);
        if (!self::system('git fetch --depth 1 origin master'))
        {
            return !self::system('git reset --hard FETCH_HEAD');
        }
        return false;
    }

    function getrev_git_ro($cfg)
    {
        $dest = $cfg['path'];
        return trim(self::system("git --git-dir \"$dest/.git\" rev-parse HEAD", true));
    }

    /**
     * Normal git checkout - with full history
     */

    function install_git_rw($cfg)
    {
        $branch = !empty($cfg['branch']) ? $cfg['branch'] : 'master';
        $dest = $cfg['path'];
        $repo = $cfg['repo'];
        return !system("git clone --branch \"$branch\" \"$repo\" \"$dest\"");
    }

    function update_git_rw($cfg)
    {
        $dest = $cfg['path'];
        chdir($dest);
        return !system("git pull origin");
    }

    function getrev_git_rw($cfg)
    {
        $dest = $cfg['path'];
        return trim(self::system("git --git-dir \"$dest/.git\" rev-parse HEAD", true));
    }
}

function ini_encode($ini, $allow_sections = true)
{
    $r = '';
    if ($allow_sections && is_array(reset($ini)))
    {
        foreach ($ini as $s => $a)
        {
            $r .= "[$s]\n".ini_encode($a, false)."\n";
        }
    }
    else
    {
        foreach ($ini as $k => $v)
        {
            if (is_array($v))
            {
                $i = 0;
                $assoc = false;
                foreach ($v as $sk => $sv)
                {
                    $assoc = $assoc || ($sk !== $i++);
                    $r .= $k.($assoc ? "[$sk]" : "[]")." = $sv\n";
                }
            }
            else
            {
                $r .= "$k = $v\n";
            }
        }
    }
    return $r;
}

function write_ini_file($file, $array, $allow_sections = true)
{
    return file_put_contents($file, ini_encode($array, $allow_sections));
}
