#!/usr/bin/env php
<?php

/**
 * Simple tool to manage multiple repositories.
 * Maintains distribution index with latest revisions for each subproject
 * for faster updates.
 *
 * Repo commands:
 *
 * php repo.php help
 * php repo.php [install|update|check] <distname> [<method>] [<destdir>]
 * php repo.php index
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
    JobControl::seek_to(JobControl::$maxPos);
    exit;
}
pcntl_signal(SIGINT, 'sigexit');
pcntl_signal(SIGTERM, 'sigexit');

Repo::run($argv);
sigexit();

class Repo
{
    var $cfg_dir;
    var $prefixes_file, $prefixes = array();
    var $dist = array();
    var $localindex_file, $localindex = array('params' => array(), 'revs' => array());
    var $distindex_file, $distindex = array();

    var $dist_name;
    var $method;
    var $dest_dir;
    var $no_refresh;
    var $parallel = 10;

    static $scriptName;

    /**
     * Console entry point
     */
    static function run($argv)
    {
        $options = array();
        $cmd = '';
        self::$scriptName = array_shift($argv);

        for ($i = 0; $i < count($argv); $i++)
        {
            $arg = $argv[$i];
            if ($arg === '--help' || $arg === '-h' || $arg === 'help')
            {
                $cmd = 'help';
                break;
            }
            elseif ($arg === '-n' || $arg === '--no-refresh')
            {
                $options['no_refresh'] = true;
            }
            elseif ($arg === '-j' || $arg === '--jobs')
            {
                $options['parallel'] = $argv[++$i];
            }
            elseif (!$cmd)
            {
                $cmd = $arg;
            }
            elseif (!isset($options['dist_name']))
            {
                $options['dist_name'] = $arg;
            }
            elseif (!isset($options['method']))
            {
                $options['method'] = $arg;
            }
            elseif (!isset($options['dest_dir']))
            {
                $options['dest_dir'] = $arg;
            }
        }

        if ($cmd === 'install')
        {
            $cmd = 'update';
        }
        if (!$cmd || $cmd === 'help')
        {
            self::printHelp();
        }
        elseif ($cmd === 'update' || $cmd === 'check' || $cmd == 'index')
        {
            $repo = new Repo($options);
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

php $s [OPTIONS] install|update <distname> [<method> [<dest_dir>]]
    Install/update distribution <distname> using <method> (default 'ro').
    Optionally set destination to <dest_dir> (relative to $dir).
    Update is fast: first configuration is resreshed from the repository,
    then modules for which revision in the local index differs
    from revision in the distribution index are updated.

php $s [OPTIONS] check [<distname> [<method> [<dest_dir>]]]
    Force update of all modules of last installed distribution.

php $s index
    Save currently checked out revisions into the distribution index.

OPTIONS:

-n or --no-refresh
    Do not refresh configuration repository before running the command.
-j N or --jobs N
    Run maximum N jobs in parallel (default is 10).

Supported revision control systems (vcs/method):
    git/ro: fast readonly clones without full history (for installation)
    git/rw: slow read-write clones with full history (for development)
";
    }

    /**
     * Constructor
     */
    function __construct(array $options)
    {
        foreach (array('dist_name', 'method', 'dest_dir', 'no_refresh', 'parallel') as $k)
        {
            if (isset($options[$k]))
            {
                $this->$k = $options[$k];
            }
        }
        $this->cfg_dir = dirname(__FILE__);
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
        if (!$this->method)
        {
            if (!empty($this->localindex['params']['method']))
            {
                $this->method = $this->localindex['params']['method'];
            }
            else
            {
                $this->method = 'ro';
            }
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
        if ($rev)
        {
            if (!isset($this->distindex[$path]) || $this->distindex[$path] !== $rev)
            {
                JobControl::print_line_for($path, "latest version updated to $rev");
                $this->distindex[$path] = $rev;
            }
            $this->localindex['revs'][$this->dist[$path]['path']] = $rev;
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
            // Refresh configuration repository
            $selftime = filemtime(__FILE__);
            if (file_exists($this->cfg_dir.'/.git/shallow'))
            {
                // Read-only update of configuration repository
                self::update_git_ro(array('path' => $this->cfg_dir), false, false);
            }
            else
            {
                // Pull to configuration repository and check for conflicts
                chdir($this->cfg_dir);
                $code = JobControl::spawn(
                    'git checkout -- '.$this->dist_name.'-index.ini'.
                    ' && git pull'.
                    ' && git checkout --theirs -- '.$this->dist_name.'-index.ini',
                    false, false
                );
                if ($code)
                {
                    print "You have conflicting changes in config repository, do 'git pull' manually\n";
                    exit(9);
                }
                $status = JobControl::shell_exec('git status --porcelain -uno');
                foreach (explode("\n", $status) as $line)
                {
                    $st = explode(' ', trim($line));
                    if (count($st) > 1)
                    {
                        list($st, $fn) = $st;
                        if (($st == 'DD' || substr($st, 0, 1) == 'U' || substr($st, 1, 1) == 'U') &&
                            $line !== $this->dist_name.'-index.ini')
                        {
                            print "There are unmerged paths, please resolve conflicts before using repo\n$status";
                            exit(8);
                        }
                    }
                }
            }
            clearstatcache();
            if (filemtime(__FILE__) > $selftime)
            {
                global $argv;
                print __FILE__." changed, restarting\n";
                $run = $argv;
                if (substr($_SERVER['_'], -3) != 'php')
                {
                    array_unshift($run, $_SERVER['_']);
                }
                system(implode(' ', array_map('escapeshellarg', $run)));
                exit;
            }
        }
    }

    function load_config()
    {
        $this->parse_config();
        $this->parse_prefixes();
        $this->parse_distindex();
        $this->set_paths();
        $this->rewrite_prefixes();
    }

    function index()
    {
        $this->load_config();
        foreach ($this->dist as $path => $cfg)
        {
            $suff = $cfg['vcs'].'_'.$this->method;
            $getrev = "getrev_$suff";
            $error = true;
            $rev = self::$getrev($cfg, $error);
            if ($rev)
            {
                $this->setrev($path, $rev);
            }
            else
            {
                JobControl::print_line_for($path, $error);
            }
        }
    }

    /**
     * Update modules of current distribution, for which revision in the
     * local index differs from revision in the distribution index, or
     * just all modules, if $force is true.
     */
    function update($force = false)
    {
        JobControl::init($this->parallel);
        if (!$this->no_refresh)
        {
            $this->refresh_config();
        }
        $this->load_config();
        $updated = false;
        foreach ($this->dist as $path => $cfg)
        {
            $suff = $cfg['vcs'].'_'.$this->method;
            $getrev = "getrev_$suff";
            $check = $force || !isset($this->distindex[$path]);
            $rev = NULL;
            if (!$check)
            {
                // FIXME remove hardcode
                if ($this->method == 'ro')
                {
                    $check = !isset($this->localindex['revs'][$cfg['path']]) ||
                        $this->localindex['revs'][$cfg['path']] !== $this->distindex[$path];
                }
                else
                {
                    $rev = self::$getrev($cfg);
                    $check = !$rev || $this->distindex[$path] !== $rev;
                }
            }
            if ($check)
            {
                if ($rev === NULL)
                {
                    $rev = self::$getrev($cfg);
                }
                $updated = true;
                $self = $this;
                $cb = function($code) use($getrev, $path, $cfg, $self)
                {
                    if (!$code)
                    {
                        $rev = Repo::$getrev($cfg);
                        $self->setrev($path, $rev);
                    }
                };
                if ($rev)
                {
                    $m = "update_$suff";
                }
                else
                {
                    $m = "install_$suff";
                }
                self::$m($cfg, $cb, $path);
            }
            JobControl::do_input();
        }
        while (JobControl::do_input())
        {
        }
        if (!$updated)
        {
            print "Everything up-to-date.\n";
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

    static function install_git_ro($cfg, $cb, $name)
    {
        $branch = !empty($cfg['branch']) ? $cfg['branch'] : 'master';
        $dest = $cfg['path'];
        $repo = $cfg['repo'];
        @mkdir($dest, 0777, true);
        JobControl::spawn(
            "git init \"$dest\"".
            " ; git --git-dir=\"$dest/.git\" remote add origin \"$repo\"".
            " ; git --git-dir=\"$dest/.git\" fetch --progress --depth=1 origin \"$branch\"".
            " && git --git-dir=\"$dest/.git\" --work-tree=\"$dest\" checkout --force FETCH_HEAD".
            " && git --git-dir=\"$dest/.git\" branch --force \"$branch\" FETCH_HEAD",
            $cb, $name);
    }

    static function update_git_ro($cfg, $cb, $name)
    {
        $branch = !empty($cfg['branch']) ? $cfg['branch'] : 'master';
        $dest = $cfg['path'];
        JobControl::spawn(
            "git --git-dir=\"$dest/.git\" fetch --progress --depth=1 origin \"$branch\"".
            " && git --git-dir=\"$dest/.git\" --work-tree=\"$dest\" checkout --force FETCH_HEAD".
            " && git --git-dir=\"$dest/.git\" branch --force \"$branch\" FETCH_HEAD",
            $cb, $name);
    }

    static function getrev_git_ro($cfg, &$error = NULL)
    {
        return self::getrev_git_rw($cfg, $error);
    }

    /**
     * Normal git checkout - with full history
     */

    static function install_git_rw($cfg, $cb, $name)
    {
        $branch = !empty($cfg['branch']) ? $cfg['branch'] : 'master';
        $dest = $cfg['path'];
        $repo = $cfg['repo'];
        $args = " --branch \"$branch\" \"$repo\"";
        if (file_exists($dest))
        {
            JobControl::spawn(
                "git init --bare \"$dest/.git\"".
                " ; git --git-dir=\"$dest/.git\" config core.bare false".
                " ; git --git-dir=\"$dest/.git\" remote add origin \"$repo\"".
                " ; git --git-dir=\"$dest/.git\" fetch --progress origin \"$branch\"".
                " && git --git-dir=\"$dest/.git\" branch -f \"$branch\" FETCH_HEAD".
                " && git --git-dir=\"$dest/.git\" --work-tree=\"$dest\" reset --hard \"$branch\"",
                $cb, $name);
        }
        else
        {
            @mkdir($dest, 0777, true);
            JobControl::spawn("git clone --progress $args \"$dest\"", $cb, $name);
        }
    }

    static function update_git_rw($cfg, $cb, $name)
    {
        $dest = $cfg['path'];
        $branch = !empty($cfg['branch']) ? $cfg['branch'] : 'master';
        if (file_exists("$dest/.git/shallow"))
        {
            // Upgrade readonly checkout to a readwrite one,
            // i.e. change URL and deepen the shallow clone
            $repo = $cfg['repo'];
            JobControl::spawn(
                "git --git-dir=\"$dest/.git\" config --replace-all remote.origin.url \"$repo\"".
                " ; git --git-dir=\"$dest/.git\" config --replace-all remote.origin.fetch \"+refs/heads/*:refs/remotes/origin/*\"".
                " ; git --git-dir=\"$dest/.git\" config \"branch.$branch.remote\" origin".
                " ; git --git-dir=\"$dest/.git\" config \"branch.$branch.merge\" \"refs/heads/$branch\"".
                " ; git --git-dir=\"$dest/.git\" fetch --progress --depth=1000000000 origin".
                " && git --git-dir=\"$dest/.git\" --work-tree=\"$dest\" checkout --force \"$branch\"",
                $cb, $name);
        }
        elseif ($cfg['rebase'])
        {
            // "Conditional rebase" for patch series (when A-B-C-D-E-F may become A-B-C-X-Y-Z)
            // In this case if master was F and equal to origin/master, master will be just reset to Z
            // If master was F and origin/master was E, F will be rebased on the top of Z (this means F is a new patch)
            // If master did not contain origin/master at all, update will fail
            $contains = JobControl::shell_exec("git --git-dir=\"$dest/.git\" branch --list --contains \"origin/$branch\" \"$branch\"");
            if ($contains)
            {
                $rev = trim(JobControl::shell_exec("git --git-dir=\"$dest/.git\" rev-parse \"origin/$branch\""));
                JobControl::spawn(
                    "git --git-dir=\"$dest/.git\" --work-tree=\"$dest\" fetch --progress origin".
                    "&& git --git-dir=\"$dest/.git\" --work-tree=\"$dest\" rebase --onto \"origin/$branch\" $rev \"$branch\"",
                    $cb, $name);
            }
            else
            {
                JobControl::spawn(
                    "echo Failed to update - your branch \"$branch\" has unsynced changes; exit 1",
                    $cb, $name);
            }
        }
        else
        {
            // Normal update
            JobControl::spawn(
                "git --git-dir=\"$dest/.git\" --work-tree=\"$dest\" pull --progress origin".
                "; git --git-dir=\"$dest/.git\" --work-tree=\"$dest\" checkout \"$branch\"",
                $cb, $name);
        }
    }

    static function getrev_git_rw($cfg, &$error = NULL)
    {
        $dest = $cfg['path'];
        $r = trim(JobControl::shell_exec("git --git-dir=\"$dest/.git\" rev-parse HEAD 2>&1"));
        if (strlen($r) !== 40)
        {
            if ($error)
            {
                $error = $r;
            }
            return '';
        }
        return $r;
    }
}

/**
 * Allows to run commands in parallel and print their output simultaneously
 * on different lines using escape sequences for cursor movement
 */
class JobControl
{
    static $childProcs = array();
    static $parallel = 1;
    static $queue = array();
    static $maxPos = 0, $curPos = 0, $positions = array(), $lastStr = array();
    static $termLines = 0;

    static function init($parallel = 1)
    {
        self::$parallel = $parallel;
        self::$termLines = getenv('LINES');
        if (self::$parallel < 2)
        {
            print "Job control is disabled, commands will be run in sequence\n";
        }
        elseif (strtolower(substr(php_uname(), 0, 3)) === 'win' ||
            !function_exists('pcntl_waitpid'))
        {
            print "Job control is unavailable, commands will be run in sequence\n";
            self::$parallel = 1;
        }
    }

    static function reap_children($pid = -1)
    {
        $code = 0;
        // Reap finished children
        $stopped = 0;
        while (($pid = pcntl_waitpid($pid, $st, WNOHANG)) > 0)
        {
            $code = pcntl_wexitstatus($st);
            if (!empty(self::$childProcs[$pid]))
            {
                $cb = self::$childProcs[$pid]['cb'];
                if ($cb)
                {
                    $cb($code, self::$childProcs[$pid]['capture']);
                }
                proc_close(self::$childProcs[$pid]['proc']);
                $n = self::$childProcs[$pid]['name'];
                if (!empty(self::$lastStr[$n]))
                {
                    self::seek_to($stopped);
                    $ok = $code ? 'color_fail' : 'color_ok';
                    print "\r".self::prompt($n).self::$ok(self::$lastStr[$n]);
                    $stopped++;
                }
                unset(self::$positions[$n]);
                unset(self::$childProcs[$pid]);
            }
        }
        // Move lines from finished processes up
        if ($stopped > 0 && self::$parallel > 1)
        {
            self::$curPos = -1;
            self::$maxPos -= $stopped;
            foreach (self::$childProcs as $proc)
            {
                self::$positions[$proc['name']] = ++self::$curPos;
                print "\n\r".self::prompt($proc['name']).@self::$lastStr[$proc['name']];
            }
        }
        // Spawn queued processes
        while (self::$queue && count(self::$childProcs) < self::$parallel)
        {
            call_user_func_array(__CLASS__.'::spawn', array_shift(self::$queue));
        }
        return $code;
    }

    /**
     * Just a synchronous shell_exec which ignores STDERR and returns full STDOUT contents
     */
    static function shell_exec($cmd)
    {
        $desc = array(STDIN, array('pipe', 'w'), array('file', '/dev/null', 'w'));
        $proc = proc_open($cmd, $desc, $pipes);
        $st = proc_get_status($proc);
        $contents = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        self::reap_children($st['pid']);
        return $contents;
    }

    /**
     * Spawn or enqueue a new child process
     */
    static function spawn($cmd, $callback = false, $name = '', $captureOutput = false)
    {
        if (self::$parallel < 2 || !$callback)
        {
            if ("$name" !== '')
            {
                print "$name: \n";
            }
            passthru($cmd, $st);
            if ($callback)
            {
                $callback($st);
            }
            return $st;
        }
        if (count(self::$childProcs) >= self::$parallel)
        {
            self::$queue[] = func_get_args();
            return;
        }
        $proc = proc_open($cmd, array(STDIN, array('pipe', 'w'), array('pipe', 'w')), $pipes);
        $st = proc_get_status($proc);
        $pid = $st['pid'];
        if (self::$termLines && self::$maxPos >= self::$termLines)
        {
            self::$positions[$name] = self::$maxPos-1;
            self::print_line_for($name, "started $pid");
        }
        else
        {
            self::$positions[$name] = self::$maxPos++;
            self::seek_to(self::$positions[$name]);
            self::$lastStr[$name] = "started $pid";
            print self::prompt($name)."started $pid\n";
            self::$curPos++;
        }
        self::$childProcs[$pid] = array(
            'proc' => $proc,
            'cmd' => $cmd,
            'cb' => $callback,
            'out' => $pipes[1],
            'err' => $pipes[2],
            'name' => $name,
            'capture' => $captureOutput ? '' : false,
        );
    }

    /**
     * Do one iteration of JobControl main loop
     * @return boolean false when JobControl has nothing to do anymore
     */
    static function do_input()
    {
        if (!self::$childProcs)
        {
            return false;
        }
        $r = $w = $x = $n = array();
        foreach (self::$childProcs as $pid => $proc)
        {
            $r[] = $proc['out'];
            $r[] = $proc['err'];
            $n[(int)$proc['out']] = $pid;
            $n[(int)$proc['err']] = $pid;
        }
        stream_select($r, $w, $x, 0);
        foreach ($r as $desc)
        {
            self::input_from($desc, self::$childProcs[$n[(int)$desc]]);
        }
        self::reap_children();
        return true;
    }

    static function prompt($name)
    {
        return "\x1B[K\x1B[1;36m$name: \x1B[0;37m";
    }

    static function color_ok($text)
    {
        return "\x1B[1;32m$text\x1B[0;37m";
    }

    static function color_fail($text)
    {
        return "\x1B[1;31m$text\x1B[0;37m";
    }

    static function print_line_for($name, $line)
    {
        if (!isset(self::$positions[$name]))
        {
            print "$name: $line\n";
            return;
        }
        $prompt = self::prompt($name);
        $line = str_replace("\n", "\r".$prompt, str_replace("\r", "\r".$prompt, trim($line)));
        self::seek_to(self::$positions[$name]);
        $p = strrpos($line, "\r");
        self::$lastStr[$name] = $p !== false ? substr($line, $p+1+strlen($prompt)) : $line;
        print $prompt.$line;
    }

    static function seek_to($pos)
    {
        $mv = self::$curPos-$pos;
        if ($mv != 0)
        {
            $mv = $mv > 0 ? $mv.'A' : (-$mv).'B';
            self::$curPos = $pos;
            print "\x1B[$mv";
        }
        print "\r";
    }

    static function input_from($fp, &$proc)
    {
        $line = @fread($fp, 4096);
        if (!$line)
        {
            return false;
        }
        if ($proc['capture'] !== false)
        {
            $proc['capture'] .= $line;
        }
        self::print_line_for($proc['name'], $line);
        return true;
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
