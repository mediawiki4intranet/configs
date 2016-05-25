<?php

// MediaWiki4Intranet configuration base for external (WWW) installations
// (c) Stas Fomin, Vitaliy Filippov 2008-2013

require_once(dirname(__FILE__).'/ServerSettings.php');

$wgScriptPath = '';
$wgUsePathInfo = true;
$wgArticlePath = "/$1";

# Для коротких URL (http://domain.com/ArticleName) нужно использовать mod_rewrite.
# Конфигурация:
#
# RewriteCond %{THE_REQUEST} ^\S+\s*/*index.php/
# RewriteRule ^index.php/(.*)$ /$1 [R=301,L,NE]
# RewriteCond %{REQUEST_FILENAME} !-f
# RewriteCond %{REQUEST_FILENAME} !-d
# RewriteRule ^(.*)$ index.php/$1 [L,B,QSA]
#
# Подробнее см. http://wiki.4intra.net/Mediawiki4Intranet, секция "Короткие URL"

// OpenID is disabled by default because it opens a way for spammers
//require_once("$IP/extensions/OpenID/OpenID.php");

$wgCookieExpiration = 30 * 86400;

$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['*']['delete'] = false;
$wgGroupPermissions['*']['undelete'] = false;
$wgGroupPermissions['*']['createpage'] = false;
$wgGroupPermissions['*']['createtalk'] = false;
$wgGroupPermissions['*']['import'] = false;
$wgGroupPermissions['*']['importupload'] = false;
$wgGroupPermissions['*']['wl-postcomment'] = false;
$wgGroupPermissions['user']['delete'] = false;
$wgGroupPermissions['user']['undelete'] = false;
$wgGroupPermissions['user']['createpage'] = false;
$wgGroupPermissions['user']['createtalk'] = false;
$wgGroupPermissions['user']['movefile'] = false;
$wgGroupPermissions['user']['wl-postcomment'] = false;
$wgGroupPermissions['autoconfirmed']['createpage'] = true;
$wgGroupPermissions['autoconfirmed']['createtalk'] = true;
$wgGroupPermissions['autoconfirmed']['import'] = true;
$wgGroupPermissions['autoconfirmed']['importupload'] = false;
$wgGroupPermissions['autoconfirmed']['wl-postcomment'] = true;
$wgGroupPermissions['sysop']['createpage'] = true;
$wgGroupPermissions['sysop']['createtalk'] = true;
$wgGroupPermissions['bureaucrat']['createpage'] = true;
$wgGroupPermissions['bureaucrat']['createtalk'] = true;
$wgEmailConfirmToEdit = true;

require_once('extensions/ListFeed/ListFeed.php');
$egListFeedFeedUrlPrefix = '/rss';
$egListFeedFeedDir = $IP.'/rss';

require_once('extensions/ConfirmEdit/ConfirmEdit.php');
require_once('extensions/WikiKCaptcha/WikiKCaptcha.php');
$wgCaptchaClass = 'WikiKCaptcha';

require_once('extensions/SimpleAntiSpamReg/SimpleAntiSpamReg.php');

// In bad cases, use the following:
//$wgAutoConfirmAge = 86400 * 4; # User must wait 4 days after registration before editing
