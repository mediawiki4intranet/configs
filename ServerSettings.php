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
