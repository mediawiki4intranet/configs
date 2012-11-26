<?php

/**
 * This script provides page title support for deleteOldRevisions.php.
 * USAGE:
 *  php deleteOldRevisions_Title.php --delete --page_title 'Test1' 'Talk:Test1'
 *
 * This version is only compatible with MediaWiki >= 1.16.
 *
 * @file
 * @ingroup Maintenance
 * @author Vitaliy Filippov <vitalif@mail.ru>
 */

echo "Title support for deleteOldRevisions.php, MW >= 1.16\n";

$orig_argv = $argv;
foreach ($argv as $arg)
{
    if ($arg == '--help')
    {
        print <<<EOF
This script is functionally equal to standard maintenance/deleteOldRevisions.php,
except that it has support for specifying pages as titles, not as IDs.
To do so, specify page titles after a --page_title option.
Example:
    php $argv[0] --delete --page_title 'Test1' 'Talk:Test1'

Help for deleteOldRevisions.php:
EOF;
        require_once(dirname(__FILE__)."/../../maintenance/deleteOldRevisions.php");
        exit;
    }
}

require_once(dirname(__FILE__)."/../../maintenance/commandLine.inc");

$pages = array();
$proc = false;
$new_argv = array();
foreach ($orig_argv as $page)
{
    if ($page == '--page_title')
    {
        $proc = true;
        $new_argv[] = '--page_id';
        print "Found --page_title, translating titles to IDs:\n";
    }
    elseif ($proc && !is_integer($page))
    {
        if (substr($page, 0, 2) == '--')
        {
            $proc = false;
            $new_argv[] = $page;
        }
        else
        {
            $title = Title::newFromText($page);
            if ($title && ($id = $title->getArticleID()))
            {
                print "    $page translated into ID: $id\n";
                $new_argv[] = $id;
            }
            else
            {
                print "    $page does not exist\n";
                exit;
            }
        }
    }
    else
        $new_argv[] = $page;
}
$argv = $new_argv;

require_once(dirname(__FILE__)."/../../maintenance/deleteOldRevisions.php");

require(DO_MAINTENANCE);
