<?php
/**
 * @file
 * @ingroup Maintenance
 */

// Options we will use
$options = array('list', 'quick', 'help', 'dry');
$optionsWithArgs = array('where', 'set', 'values');

require_once(dirname(__FILE__).'/../../maintenance/commandLine.inc');

/**
 * @ingroup Maintenance
 */
class userOptions
{
    var $mQuick, $mDry, $mWhere, $mSet, $mOption, $mMode;

    /** load script options in the object */
    function __construct($opts, $args)
    {
        $this->mQuick = isset($opts['quick']);

        // Set object properties, specially 'mMode' used by run()
        if (isset($opts['list']))
            $this->mMode = 'listOptions';
        elseif (isset($opts['values']))
        {
            $this->mMode = 'listValues';
            $this->mOption = $opts['values'];
        }
        elseif ($opts['where'] && $opts['set'])
        {
            $this->mMode = 'updateOptions';
            $this->mDry = isset($opts['dry']);
            $this->mWhere = userOptions::keyvalue($opts['where']);
            $this->mSet = userOptions::keyvalue($opts['set']);
        }
        else
            userOptions::showUsageAndExit();

        return true;
    }

    // Dumb stuff to run a mode.
    function run()
    {
        $this->{$this->mMode}();
    }

    /** List default options and their value */
    function listOptions()
    {
        $def = User::getDefaultOptions();
        ksort($def);
        $maxOpt = 0;
        foreach($def as $opt => $value)
            $maxOpt = max($maxOpt, strlen($opt));
        foreach($def as $opt => $value)
            printf("%-{$maxOpt}s: %s\n", $opt, $value);
    }

    /** List option values */
    function listValues()
    {
        $ret = array();
        $defaultOptions = User::getDefaultOptions();
        if (!array_key_exists ($this->mOption, $defaultOptions))
        {
            print "Invalid user option. Use --list to see valid choices\n";
            exit;
        }

        // We list user by user_id from one of the slave database
        $dbr = wfGetDB(DB_SLAVE);
        $result = $dbr->select('user', array('user_id'), array(), __METHOD__);

        while ($id = $dbr->fetchObject($result))
        {
            $user = User::newFromId($id->user_id);
            // Get the options and update stats
            if ($this->mOption)
            {
                $userValue = $user->getOption($this->mOption);
                if ($userValue != $defaultOptions[$this->mOption])
                    @$ret[$this->mOption][$userValue]++;
            }
            else
            {
                foreach ($defaultOptions as $name => $defaultValue)
                {
                    $userValue = $user->getOption($name);
                    if ($userValue != $defaultValue)
                        @$ret[$name][$userValue]++;
                }
            }
        }

        foreach ($ret as $optionName => $usageStats)
        {
            print "Usage statistics for <$optionName> (default: '{$defaultOptions[$optionName]}'):\n";
            foreach($usageStats as $value => $count)
                print " $count user(s): '$value'\n";
            print "\n";
        }
    }

    /** Change options */
    function updateOptions()
    {
        $this->warn();

        // We list user by user_id from one of the slave database
        $dbr = wfGetDB(DB_SLAVE);
        $result = $dbr->select('user', array('user_id'), array(),__METHOD__);

        $anything = false;
        while ($id = $dbr->fetchObject($result))
        {
            $user = User::newFromId($id->user_id);
            $username = $user->getName();

            $change = true;
            foreach ($this->mWhere as $k => $v)
            {
                $curValue = $user->getOption($k);
                // hack for boolean values
                if ($curValue != $v && ($curValue != '' || $v != '0'))
                {
                    $change = false;
                    break;
                }
            }

            if ($change)
            {
                foreach ($this->mSet as $k => $v)
                {
                    print "Will set option $k=$v for User:$username\n";
                    if (!$this->mDry)
                        $user->setOption($k, $v);
                }
                if (!$this->mDry)
                    $user->saveSettings();
                $anything = true;
            }
        }
        if (!$anything)
            print "No changes\n";
    }

    /** Return an array of option names */
    static function getDefaultOptionsNames()
    {
        $def = User::getDefaultOptions();
        $ret = array();
        foreach($def as $optname => $defaultValue)
            array_push($ret, $optname);
        return $ret;
    }

    /* Split key=value,key=value,key=value string into an array */
    static function keyvalue($s)
    {
        $a = explode(',', $s);
        $b = array();
        foreach ($a as $c)
        {
            list($k, $v) = explode('=', $c);
            $b[$k] = $v;
        }
        return $b;
    }

    static function showUsageAndExit()
    {
print <<<USAGE
This script pass through all users and change one of their options.
The new option is NOT validated.

USAGE:
    php userOptions.php --list
    php userOptions.php --values <option>
    php userOptions.php --where <key=value,key=value,...> --set <key=value,key=value,...>

Switches:
    --list            : list available user options and their default values

    --values <option> : list all <option> values and update statistics for them
    --all-values      : the same for all available options

    --where <where>   : the old value set
    --set <set>       : the new value set

Options:
    --quick : hides the 5 seconds warning
    --dry   : dry run: only print pending changes without actually applying them

USAGE;
        exit(0);
    }

    /** The warning message and countdown */
    function warn()
    {
        if ($this->mQuick)
            return true;
        if ($this->mDry)
            print "The script is about to print pending changes";
        else
            print "The script is about to change options";
        print " for";
        $i = " users having ";
        foreach ($this->mWhere as $k => $v)
        {
            print $i . $k . '=' . $v;
            $i = " and ";
        }
        if ($i != " and ")
            print " all users";
        print ".\nThe following options will be changed: ";
        $i = '';
        foreach ($this->mSet as $k => $v)
        {
            print $i . $k . '=' . $v;
            $i = ", ";
        }
        print ".\n\nAbort with control-c in the next five seconds...";
        wfCountDown(5);
        return true;
    }
}

$uo = new userOptions($options, $args);
$uo->run();
