<?php

$dir = dirname($_SERVER['PHP_SELF']);
require_once "$dir/../../maintenance/commandLine.inc";

class PrintDBCfg
{
	function run()
	{
		global $wgDBserver, $wgDBuser, $wgDBpassword, $wgDBname;
		print '__DB_CONFIG__ = '.serialize(array(
			'server' => $wgDBserver,
			'user' => $wgDBuser,
			'pass' => $wgDBpassword,
			'db' => $wgDBname,
		));
	}

	function help()
	{
		echo "Prints DB connection settings for this MediaWiki\n";
	}
}

$app = new PrintDBCfg($options);
$app->run();
