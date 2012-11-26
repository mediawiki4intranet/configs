<?php
/**
 * @file
 * @ingroup Maintenance
 */

$dir = dirname($_SERVER['PHP_SELF']);
require_once "$dir/../../maintenance/commandLine.inc";

class ExtLowercaser
{
	function __construct( $args )
	{
		if ($args['quiet'])
			$this->quiet = true;
	}
	
	function out($s)
	{
		if (!$this->quiet)
			print $s;
	}
	
	function run()
	{
		global $wgUser;
		$wgUser = User::newFromName('WikiSysop');
		$dbr = wfGetDB(DB_MASTER);
		$res = $dbr->select('image', '*', '1', __METHOD__);
		$file = NULL;
		$lastfilename = NULL;
		while ($img = $dbr->fetchRow($res))
		{
			$name = $img['img_name'];
			if (preg_match('/\.[^\.]+$/is', $name, $m) &&
				$name != ($newname = substr($name, 0, strlen($name)-strlen($m[0])).strtolower($m[0])))
			{
				$ot = Title::makeTitle(NS_FILE, $name);
				$nt = Title::makeTitle(NS_FILE, $newname);
				print $ot->getPrefixedText() . " --> " . $nt->getPrefixedText() . "\n";
				$error = $ot->moveTo($nt, true, "Fix upper-case file extensions", true);
				if ($error !== true)
				{
					print "Error renaming $name to $newname\n";
					print_r($error);
				}
			}
		}
		$dbr->freeResult($res);
		$dbr->commit();
	}
	
	function help() {
		echo <<<END
Do batch rename of uploaded files so they will have lower-case extensions.

Usage:
php extensions-to-lowercase.php

END;
	}
}

print "Going to rename upper or mixed-case uploaded file extensions into lower-case for ".wfWikiID()."\n";

if (!isset($options['quick']))
{
	print "Abort with control-c in the next five seconds... ";
	wfCountDown(5);
}

$app = new ExtLowercaser($options);
$app->run();
