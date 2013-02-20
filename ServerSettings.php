<?php

// MediaWiki4Intranet configuration base for internal UNIX installations
// (c) Stas Fomin, Vitaliy Filippov 2008-2011

require_once(dirname(__FILE__).'/BaseSettings.php');

$wgPageShowWatchingUsers = true;

require_once($IP.'/extensions/EnotifDiff/EnotifDiff.php');

$wgEnableEmail         = true;
$wgEnableUserEmail     = true;
$wgEnotifUserTalk      = true; // UPO
$wgEnotifWatchlist     = true; // UPO
$wgEmailAuthentication = true;
$wgEnotifMinorEdits    = true;

$wgEmergencyContact    = "stas@custis.ru";
$wgPasswordSender      = "wiki-daemon@custis.ru";

$wgAllowExternalImages     = true;
$wgAllowExternalImagesFrom = array(
    'http://penguin.office.custis.ru/',
    'http://svn.office.custis.ru/',
    'http://plantime.office.custis.ru/'
);

// Bug 57350 - PDF and Djvu (UNIX only)
require_once($IP.'/extensions/PdfHandler/PdfHandler.php');

$wgDjvuDump = "djvudump";
$wgDjvuRenderer = "ddjvu";
$wgDjvuTxt = "djvutxt";
$wgDjvuPostProcessor = "ppmtojpeg";
$wgDjvuOutputExtension = 'jpg';

$wgPdfProcessor = 'nice -n 20 gs';
$wgPdftoText = ''; // it's useless, disable
$wgPdfCreateThumbnailsInJobQueue = true;
$wgPdfDpiRatio = 2;

$wgDiff3 = '/usr/bin/diff3';

// Bug 82496 - enable scary (cross-wiki) transclusions
$wgEnableScaryTranscluding = true;

// Bug 107222 - TikaMW. TODO: enable also on Windows along with Sphinx
require_once($IP.'/extensions/TikaMW/TikaMW.php');

///////////////////////////////////////////////
# Allow to override per-page and per-category rights
$haclgCombineMode = HACL_COMBINE_OVERRIDE;

$wgGroupPermissions['user']['skipcaptcha'] = true; 
$wgCaptchaTriggers['badlogin']      = true;

$wgGroupPermissions['*']['interwiki'] = false;
$wgGroupPermissions['sysop']['interwiki'] = true;

# Pretect this wiki from viewing by unregs
# Disable reading by anonymous users
# (already disabled by IntraACL, but let's repeat just in case)
$wgGroupPermissions['*']['read'] = false;
$wgGroupPermissions['*']['edit'] = false;
# But allow them to read e.g., these pages:
$wgWhitelistRead = array("Main Page", "Special:UserLogin", "Special:Userlogin", "Help:Contents", "-");

# Allow to upload not only the "wanted" documents
$wgStrictFileExtensions = false;
# Extend "wanted" document list
$wgFileExtensions = array_merge($wgFileExtensions, array('odt','odp','ods','xlsx','docx','pptx','3ga'));

require_once("$IP/extensions/Propiska/Propiska.php");

# Open public logs
$egFavRatePublicLogs = true;
$egFavRateLogVisitors = true;

#$wgMainCacheType=CACHE_NONE;
#$wgMessageCacheType=CACHE_DB;

$wgLocalisationCacheConf = array(
  'class' => 'LocalisationCache',
  'store' => 'db',
); 

$wgMimeTypeBlacklist = array(
	# HTML may contain cookie-stealing JavaScript and web bugs
	'text/javascript', 'text/x-javascript',  'application/x-shellscript',
	# PHP scripts may execute arbitrary code on the server
	'application/x-php', 'text/x-php',
	# Other types that may be interpreted by some servers
	'text/x-python', 'text/x-perl', 'text/x-bash', 'text/x-sh', 'text/x-csh',
	# Client-side hazards on Internet Explorer
	'text/scriptlet', 'application/x-msdownload',
	# Windows metafile, client-side vulnerability on some systems
	'application/x-msmetafile',
);
 
$wgDisableUploadScriptChecks = true; 

$wgMaxUploadSize = 70*1024*1024;
$wgLogo = "/images/6/6f/ThisWikiLogo.png";

$wgUploadPath = '/img_auth.php';
