<?php

// MediaWiki4Intranet configuration base for internal UNIX installations
// (c) Stas Fomin, Vitaliy Filippov 2008-2011
require_once(dirname(__FILE__).'/BaseSettings.php');

$wgPageShowWatchingUsers = true;

$wgEnableEmail         = true;
$wgEnableUserEmail     = true;
$wgEnotifUserTalk      = true; // UPO
$wgEnotifWatchlist     = true; // UPO
$wgEmailAuthentication = true;
$wgEnotifMinorEdits    = true;

// Bug 57350 - PDF and Djvu (UNIX only)
require_once($IP.'/extensions/PdfHandler/PdfHandler.php');

$wgDjvuDump = "djvudump";
$wgDjvuRenderer = "ddjvu";
$wgDjvuTxt = "djvutxt";
$wgDjvuPostProcessor = "ppmtojpeg";
$wgDjvuOutputExtension = 'jpg';

$wgPdfToCairo = 'nice -n 20 pdftocairo';
$wgPdftoText = ''; // it's useless, disable
$wgPdfCreateThumbnailsInJobQueue = true;

$wgDiff3 = '/usr/bin/diff3';

// Bug 82496 - enable scary (cross-wiki) transclusions
$wgEnableScaryTranscluding = true;

// Bug 107222 - TikaMW. TODO: enable also on Windows along with Sphinx
require_once($IP.'/extensions/TikaMW/TikaMW.php');

// Bug 229698
$egS5DefaultStyle="custis";
$egS5Scaled=1;
$egS5SlideHeadingMark="";


//Bug 231383
require_once "$IP/extensions/TextExtracts/TextExtracts.php";
require_once "$IP/extensions/PageImages/PageImages.php";
require_once "$IP/extensions/Popups/Popups.php";
