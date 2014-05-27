<?php

// MediaWiki4Intranet configuration base for all MW installations (UNIX, Windows)
// Contains many useful configuration hints
// (c) Stas Fomin, Vitaliy Filippov 2008-2013

setlocale(LC_ALL, 'ru_RU.UTF-8');
setlocale(LC_NUMERIC, 'C');

if (defined('MW_INSTALL_PATH'))
    $IP = MW_INSTALL_PATH;
else
{
    foreach (debug_backtrace() as $frame)
        if (strtolower(substr($frame['file'], -strlen('LocalSettings.php'))) == 'localsettings.php')
            $IP = realpath(dirname($frame['file']));
    if (!$IP)
        $IP = realpath(dirname(__FILE__) . '/..');
}

$path = array($IP, "$IP/includes", "$IP/includes/specials","$IP/languages");
set_include_path(implode(PATH_SEPARATOR, $path) . PATH_SEPARATOR . get_include_path());

require_once($IP . '/includes/DefaultSettings.php');

// Powered by 4intranet icon
$wgExtensionFunctions[] = 'efPoweredBy4Intranet';

# Default sitename and URL base path
$wgSitename         = "CustisWiki";
$wgScriptPath       = "/wiki";
$wgScriptExtension  = ".php";
$wgUsePathInfo      = true;

$wgEnableEmail      = false;
$wgEnableUserEmail  = false;

$wgDBtype           = "mysql";
$wgDBserver         = "localhost";

$wgDBname           = "wiki";
$wgDBuser           = "wiki";
$wgDBpassword       = "wiki";
$wgDBadminuser      = "wiki";
$wgDBadminpassword  = "wiki";

$wgDBprefix         = "";

$wgDBTableOptions   = "ENGINE=InnoDB, DEFAULT CHARSET=utf8";
$wgDBmysql5         = true;

$wgEnableUploads    = true;
$wgMaxUploadSize    = 1024 * 1024 * 512; # 512MB
$wgAllowExternalImages = true;

$wgLocalInterwiki   = $wgSitename;
$wgLocaltimezone    = "Europe/Moscow";

$wgRightsPage = "";
$wgRightsUrl = "";
$wgRightsText = "";
$wgRightsIcon = "";
$wgRightsCode = "";

$wgDiff3 = "diff3";
$wgImageMagickConvertCommand = "convert";

# When you make changes to this configuration file, this will make
# sure that cached pages are cleared.
$wgCacheEpoch = max( $wgCacheEpoch, gmdate( 'YmdHis', @filemtime( __FILE__ ) ) );
$wgMainCacheType = empty( $_SERVER['SERVER_NAME'] ) ? CACHE_NONE : CACHE_ACCEL;
$wgParserCacheType = $wgMessageCacheType = $wgMainCacheType;
$wgMemCachedServers = array();

$wgRawHtml = true;
$wgAllowUserJs = true;
$wgUseAjax = true;

$wgFileExtensions = array(
    'png', 'gif', 'jpg', 'jpeg', 'svg',
    'zip', 'rar', '7z', 'gz', 'bz2', 'xpi',
    'doc', 'docx', 'ppt', 'pptx', 'pps', 'ppsx', 'xls', 'xlsx', 'vsd',
    'djvu', 'pdf', 'xml', 'mm'
);

// Allow URL uploads
$wgAllowCopyUploads = true;
$wgCopyUploadsFromSpecialUpload = true;
$wgStrictFileExtensions = false;

// Do not deny img_auth.php access if wiki has public read permission! (IntraACL may still deny access)
$wgImgAuthPublicTest = false;

array_push($wgUrlProtocols, "file://");
$wgLanguageCode = "ru";

$wgSMTP = false;
$wgShowExceptionDetails = true;

// Put settings (public static field changes) for autoloaded classes here
$wgClassSettings = array();
if (version_compare(PHP_VERSION, '5.3', '>='))
{
    function wfAutoloadClassSettings($class)
    {
        global $wgClassSettings;
        if (isset($wgClassSettings[$class]))
        {
            AutoLoader::autoload($class);
            foreach ($wgClassSettings[$class] as $name => $value)
            {
                $class::$$name = $value;
            }
        }
    }
    spl_autoload_register('wfAutoloadClassSettings', true, true);
}

require_once($IP.'/extensions/ParserFunctions/ParserFunctions.php');
$wgPFStringLengthLimit = 4000;
$wgPFEnableStringFunctions = true;
require_once($IP.'/extensions/RegexParserFunctions/RegexParserFunctions.php');
require_once($IP.'/extensions/CharInsert/CharInsert.php');
require_once($IP.'/extensions/CharInsertList/CharInsertList.php');
require_once($IP.'/extensions/Cite/Cite.php');
require_once($IP.'/extensions/SyntaxHighlight_GeSHi/SyntaxHighlight_GeSHi.php');
require_once($IP.'/extensions/CategoryTree/CategoryTree.php');
$wgCategoryTreeMaxDepth = array(CT_MODE_PAGES => 100, CT_MODE_ALL => 100, CT_MODE_CATEGORIES => 100);

// CatCatGrouping is enabled, but subcategorized lists are disabled by default
// So it only does grouping of adjacent characters in alphabet lists
require_once($IP.'/extensions/CatCatGrouping/CatCatGrouping.php');
$wgCategorySubcategorizedList = false;

$wgSubcategorizedAlwaysExclude = array('CustisWikiToLib',
    'CustisWikiToSMWiki', 'CustisWikiToSBWiki', 'CustisWikiToRDWiki',
    'CustisWikiToGZWiki', 'CustisWikiToHRWiki', 'CustisWikiToDPWiki',
    'CustisWikiToORWiki', 'CustisWikiToCBWiki');

$wgGroupPermissions['*']['interwiki'] = false;
$wgGroupPermissions['sysop']['interwiki'] = true;
$wgGroupPermissions['sysop']['override-export-depth'] = true;

require_once($IP.'/extensions/Interwiki/Interwiki.php');
require_once($IP.'/extensions/WikiCategoryTagCloud/WikiCategoryTagCloud.php');

require_once($IP.'/extensions/DocExport/DocExport.php');
require_once($IP.'/extensions/CustisScripts/CustisScripts.php');
require_once($IP.'/extensions/BatchEditor/BatchEditor.php');
require_once($IP.'/extensions/MarkupBabel/MarkupBabel.php');
require_once($IP.'/extensions/CategoryTemplate/CategoryTemplate.php');
require_once($IP.'/extensions/DeleteBatch/DeleteBatch.php');
require_once($IP.'/extensions/FullLocalImage/FullLocalImage.php');

// New editing toolbar: WikiEditor
require_once($IP.'/extensions/WikiEditor/WikiEditor.php');
$wgDefaultUserOptions['usebetatoolbar'] = 1;
$wgDefaultUserOptions['usebetatoolbar-cgd'] = 1;
$wgDefaultUserOptions['wikieditor-preview'] = 1;
$wgDefaultUserOptions['wikieditor-publish'] = 0; // does not work in REL1_20!

require_once($IP.'/extensions/WikiEditorInplace/WikiEditorInplace.php');

require_once($IP.'/extensions/SVGEdit/SVGEdit.php');

$wgGroupPermissions['bureaucrat']['usermerge'] = true;
require_once($IP.'/extensions/UserMerge/UserMerge.php');
require_once($IP.'/extensions/Renameuser/Renameuser.php');

require_once($IP.'/extensions/MMHandler/MMHandler.php');
/* for mindmap uploads */
$wgForbiddenTagsInUploads = array('<object', '<param', '<embed', '<script');

require_once($IP.'/extensions/PagedTiffHandler/PagedTiffHandler.php');
unset($wgAutoloadClasses['PagedTiffHandlerSeleniumTestSuite']);

require_once($IP.'/extensions/Mp3Handler/Mp3Handler.php');

require_once($IP.'/extensions/Dia/Dia.php');

$wgAllowCategorizedRecentChanges = true;
$wgFeedLimit = 500;

require_once($IP.'/extensions/MergeConflicts/MergeConflicts.php');
require_once($IP.'/extensions/AllNsSuggest/AllNsSuggest.php');
require_once($IP.'/extensions/NewPagesEx/NewPagesEx.php');
require_once($IP.'/extensions/Calendar/Calendar.php');
require_once($IP.'/extensions/SimpleTable/SimpleTable.php');
require_once($IP.'/extensions/MagicNumberedHeadings/MagicNumberedHeadings.php');
// Numbered headings by default for new users
$wgDefaultUserOptions['wpnumberheadings'] = 1;

require_once($IP.'/extensions/MediaFunctions/MediaFunctions.php');
require_once($IP.'/extensions/AllowGetParamsInWikilinks/AllowGetParamsInWikilinks.php');
require_once($IP.'/extensions/WikiBookmarks/WikiBookmarks.php');
require_once($IP.'/extensions/SWFUpload/SWFUpload.php');
require_once($IP.'/extensions/SupaMW/SupaMW.php');
require_once($IP.'/extensions/UserMagic/UserMagic.php');
require_once($IP.'/extensions/S5SlideShow/S5SlideShow.php');
require_once($IP.'/extensions/UserMessage/UserMessage.php');
require_once($IP.'/extensions/PlantUML/PlantUML.php');
require_once($IP.'/extensions/HttpAuth/HttpAuth.php');
require_once($IP.'/extensions/SimpleForms/SimpleForms.php'); /* useful at least for {{#request:...}} */
require_once($IP.'/extensions/WhoIsWatching/WhoIsWatching.php');
require_once($IP.'/extensions/Polls/poll.php');
require_once($IP.'/extensions/Shortcuts/Shortcuts.php');
require_once($IP.'/extensions/TopCategoryLinks/TopCategoryLinks.php');
require_once($IP.'/extensions/RemoveConfidential/RemoveConfidential.php');
require_once($IP.'/extensions/CustomToolbox/CustomToolbox.php');
require_once($IP.'/extensions/CustomSidebar/CustomSidebar.php');
require_once($IP.'/extensions/FavRate/FavRate.php');
require_once($IP.'/extensions/SlimboxThumbs/SlimboxThumbs.php');
require_once($IP.'/extensions/Spoil/Spoil.php');
require_once($IP.'/extensions/Duplicator/Duplicator.php');
require_once($IP.'/extensions/PopupWhatlinkshere/PopupWhatlinkshere.php');
require_once($IP.'/extensions/Variables/Variables.php');
require_once($IP.'/extensions/LinkAutocomplete/LinkAutocomplete.php');
require_once($IP.'/extensions/PageSnapshots/PageSnapshots.php');
require_once($IP.'/extensions/LessUsedCategories/LessUsedCategories.php');

# Drafts
require_once($IP.'/extensions/Drafts/Drafts.php');
$egDraftsAutoSaveWait = 30;   // half a minute

# FlvHandler
require_once($IP.'/extensions/FlvHandler/FlvHandler.php');

# SemanticMediaWiki
if (!defined('WIKI4INTRANET_DISABLE_SEMANTIC'))
{
    require_once($IP.'/extensions/Validator/Validator.php');
    $smwgNamespaceIndex = 120;
    require_once($IP.'/extensions/SemanticMediaWiki/SemanticMediaWiki.php');
    require_once($IP.'/extensions/SemanticInternalObjects/SemanticInternalObjects.php');
    $smwgQMaxSize = 128;
    $smwgQMaxDepth = 16;

    $wgExtensionFunctions[] = 'autoEnableSemantics';
    $wgClassSettings['SMWResultPrinter']['maxRecursionDepth'] = 15;
    function autoEnableSemantics()
    {
        global $wgServer;
        enableSemantics(str_replace('www.', '', parse_url($wgServer, PHP_URL_HOST)));
    }
}

# IntraACL
if (!defined('WIKI4INTRANET_DISABLE_INTRAACL'))
{
    require_once('extensions/IntraACL/includes/HACL_Initialize.php');
    enableIntraACL();
    $haclgInclusionDeniedMessage = '';
    $haclgEnableTitleCheck = true;
}

# MWQuizzer
require_once($IP.'/extensions/mediawikiquizzer/mediawikiquizzer.php');
$egMWQuizzerIntraACLAdminGroup = 'Group/QuizAdmin';
MediawikiQuizzer::setupNamespace(104);

# Wikilog
require_once($IP.'/extensions/Wikilog/Wikilog.php');
Wikilog::setupBlogNamespace(100);
$wgWikilogPagerDateFormat = 'ymd hms';
$wgWikilogMaxCommentSize = 0x7FFFFFFF;
$wgWikilogDefaultNotCategory = 'Скрытые';
$wgWikilogSearchDropdowns = true;
$wgWikilogCommentsOnItemPage = true;
$wgWikilogNumComments = 100;
$wgWikilogExpensiveLimit = 100;
# Enable Wikilog-style threaded talks pages everywhere
$wgWikilogCommentNamespaces = true;

# Namespaces with subpages
$wgNamespacesWithSubpages += array(
    NS_MAIN     => true,
    NS_PROJECT  => true,
    NS_TEMPLATE => true,
    NS_HELP     => true,
    NS_CATEGORY => true,
    NS_QUIZ     => true,
    NS_QUIZ_TALK => true,
);

# TemplatedPageList
require_once($IP.'/extensions/TemplatedPageList/TemplatedPageList.php');
$egSubpagelistAjaxDisableRE = '#^Блог:[^/]*$#s';

$wgMaxFilenameLength = 50;
$wgGalleryOptions['captionLength'] = 50; // 1.18

$wgSVGConverter = "inkscape";
$wgUseImageMagick = false;
$wgGDAlwaysResample = true;

require_once($IP . '/includes/GlobalFunctions.php');
if (wfIsWindows())
{
    $wgSVGConverterPath = realpath($IP."/../../app/inkscape/");
    $wgDIAConverterPath = realpath($IP."/../../app/dia/bin/");
    //$wgImageMagickConvertCommand = realpath($IP."/../../app/imagemagick")."/convert.exe";
    // Bug 48216 - Transliterate cyrillic file names of uploaded files
    $wgTransliterateUploadFilenames = true;
    $wgSphinxQL_host = '127.0.0.1';
    $wgSphinxQL_port = '9306';
    $wgZip = realpath("$IP/../../app/zip/zip.exe");
    $wgUnzip = realpath("$IP/../../app/zip/unzip.exe");
    $wgParserCacheType = $wgMessageCacheType = $wgMainCacheType = CACHE_DB;
}

$wgCookieExpiration = 3650 * 86400;

$wgLogo    = "$wgScriptPath/configs/logos/wiki4intranet-logo.png";
$wgFavicon = "$wgScriptPath/configs/favicons/wiki4intranet.ico";

$wgDebugLogFile = false;

$wgDefaultSkin = 'monobook';

$wgGroupPermissions['*']['edit'] = false;

$wgSphinxTopSearchableCategory = "Root";

$wgNamespacesToBeSearchedDefault = array(
    NS_MAIN => 1,
    NS_USER => 1,
    NS_FILE => 1,
    NS_HELP => 1,
    NS_CATEGORY => 1,
    NS_BLOG => 1,
);

$wgShellLocale = 'ru_RU.UTF-8';

// Memory limit is useless without cgroups - limits virtual memory size instead of really reserved
$wgMaxShellMemory = 0;
$wgMaxShellFileSize = 1024000;

$wgNoCopyrightWarnings = true;

$wgEnableMWSuggest     = true;
$wgOpenSearchTemplate  = true;

// Don't purge recent changes... (keep them for 50 years)
$wgRCMaxAge = 50 * 365 * 86400;

// No need to restrict Intranet users from these actions
$wgGroupPermissions['user']['delete'] = true;
$wgGroupPermissions['user']['undelete'] = true;
$wgGroupPermissions['user']['movefile'] = true;
$wgGroupPermissions['user']['upload_by_url'] = true;
$wgGroupPermissions['user']['import'] = true;
$wgGroupPermissions['user']['importupload'] = true;
$wgGroupPermissions['user']['suppressredirect'] = true;
$wgGroupPermissions['sysop']['deletebatch'] = true;

// Default settings for Sphinx search
$wgSphinxSearch_weights = array('page_title' => 2, 'old_text' => 1);
$wgSphinxSearch_matches = 20;
$wgSphinxMatchAll = 1;
$wgSphinxSuggestMode = true;

$wgMaxImageArea = 5000*5000;

// Raise article size limit for oversized inclusions (10 MB)
$wgMaxArticleSize = 1024*10;

// Allow all ?action=raw content types
$wgAllowedRawCTypes = true;

// Use "wikipedia-like" search box in Vector skin
$wgDefaultUserOptions['vector-simplesearch'] = true;
$wgVectorUseSimpleSearch = true;

function efPoweredBy4Intranet()
{
    global $wgFooterIcons, $wgScriptPath;
    $wgFooterIcons['poweredby']['mediawiki4intranet'] = array(
        'src' => $wgScriptPath.'/configs/logos/poweredby-4intranet.png',
        'url' => 'http://wiki.4intra.net/MediaWiki4Intranet',
        'title' => 'Powered by MediaWiki4Intranet extension bundle',
        'alt' => 'MediaWiki4Intranet',
    );
}
