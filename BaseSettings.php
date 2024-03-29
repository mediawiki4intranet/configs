<?php

// MediaWiki4Intranet configuration base for all MW installations (UNIX, Windows)
// Contains many useful configuration hints
// (c) Stas Fomin, Vitaliy Filippov 2008-2020

setlocale(LC_ALL, 'ru_RU.UTF-8');
setlocale(LC_NUMERIC, 'C');

if (getenv('MW_INSTALL_PATH'))
    $IP = getenv('MW_INSTALL_PATH');
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

// Allow HTML e-mail (requires PEAR Mail_mime package to be installed, php-mail-mime in Debian)
$wgAllowHTMLEmail = true;

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

// Register skins
require_once("$IP/skins/CologneBlue/CologneBlue.php");
require_once("$IP/skins/Vector/Vector.php");
require_once("$IP/skins/MonoBook/MonoBook.php");
require_once("$IP/skins/Nostalgia/Nostalgia.php");
require_once("$IP/skins/Modern/Modern.php");
wfLoadSkin('cleanmonobook');
wfLoadSkin('cleanvector');
wfLoadSkin('noleftvector');
wfLoadSkin('custisru');

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

require_once($IP.'/extensions/CategoryWatch/CategoryWatch.php');

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

require_once($IP.'/extensions/MsUpload/MsUpload.php');

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
require_once($IP.'/extensions/UserMagic/UserMagic.php');
require_once($IP.'/extensions/S5SlideShow/S5SlideShow.php');
require_once($IP.'/extensions/UserMessage/UserMessage.php');
require_once($IP.'/extensions/PlantUML/PlantUML.php');
require_once($IP.'/extensions/HttpAuth/HttpAuth.php');
require_once($IP.'/extensions/AjaxLoader/AjaxLoader.php');
require_once($IP.'/extensions/RequestMagic/RequestMagic.php');
require_once($IP.'/extensions/WhoIsWatching/WhoIsWatching.php');
require_once($IP.'/extensions/Polls/Polls.php');
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
require_once($IP.'/extensions/VisioHandler/VisioHandler.php');
wfLoadExtension('SwaggerDoc');

# Page popups
require_once "$IP/extensions/TextExtracts/TextExtracts.php";
require_once "$IP/extensions/PageImages/PageImages.php";
require_once "$IP/extensions/Popups/Popups.php";

# Drafts
require_once($IP.'/extensions/Drafts/Drafts.php');
$egDraftsAutoSaveWait = 30;   // half a minute

require_once($IP.'/extensions/DrawioEditor/DrawioEditor.php');

# FlvHandler
require_once($IP.'/extensions/FlvHandler/FlvHandler.php');

# SemanticMediaWiki
if (!defined('WIKI4INTRANET_DISABLE_SEMANTIC'))
{
    $smwgNamespaceIndex = 120;
    require_once($IP.'/extensions/SemanticMediaWiki/SemanticMediaWiki.php');
    require_once($IP.'/extensions/SemanticInternalObjects/SemanticInternalObjects.php');
    require_once($IP.'/extensions/SemanticForms/SemanticForms.php');
    require_once($IP.'/extensions/SemanticFormsInputs/SemanticFormsInputs.php');
    require_once($IP.'/extensions/SemanticFormsSelect/SemanticFormsSelect.php');
    require_once($IP.'/extensions/SemanticResultFormats/SemanticResultFormats.php');
    require_once($IP.'/extensions/Arrays/Arrays.php');
    require_once($IP.'/extensions/Loops/Loops.php');
    $baseDir = $IP.'/extensions/SemanticResultFormats';
    $wgAutoloadClasses += array(
        'ProcessEdge' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'ProcessElement' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'ProcessGraph' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'ProcessNode' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'ProcessRessource' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'ProcessRole' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'SMWBibTeXEntry' => $baseDir . '/formats/bibtex/SRF_BibTeX.php',
        'SRFArray' => $baseDir . '/formats/array/SRF_Array.php',
        'SRFBibTeX' => $baseDir . '/formats/bibtex/SRF_BibTeX.php',
        'SRFBoilerplate' => $baseDir . '/formats/boilerplate/SRF_Boilerplate.php',
        'SRFCHistoricalDate' => $baseDir . '/formats/calendar/SRFC_HistoricalDate.php',
        'SRFCalendar' => $baseDir . '/formats/calendar/SRF_Calendar.php',
        'SRFD3Chart' => $baseDir . '/formats/d3/SRF_D3Chart.php',
        'SRFDygraphs' => $baseDir . '/formats/dygraphs/SRF_Dygraphs.php',
        'SRFExhibit' => $baseDir . '/formats/Exhibit/SRF_Exhibit.php',
        'SRFFiltered' => $baseDir . '/formats/Filtered/SRF_Filtered.php',
        'SRFGoogleBar' => $baseDir . '/formats/googlecharts/SRF_GoogleBar.php',
        'SRFGooglePie' => $baseDir . '/formats/googlecharts/SRF_GooglePie.php',
        'SRFGraph' => $baseDir . '/formats/graphviz/SRF_Graph.php',
        'SRFHash' => $baseDir . '/formats/array/SRF_Hash.php',
        'SRFHooks' => $baseDir . '/SemanticResultFormats.hooks.php',
        'SRFIncoming' => $baseDir . '/formats/incoming/SRF_Incoming.php',
        'SRFJitGraph' => $baseDir . '/formats/JitGraph/SRF_JitGraph.php',
        'SRFListWidget' => $baseDir . '/formats/widget/SRF_ListWidget.php',
        'SRFMath' => $baseDir . '/formats/math/SRF_Math.php',
        'SRFOutline' => $baseDir . '/formats/outline/SRF_Outline.php',
        'SRFOutlineItem' => $baseDir . '/formats/outline/SRF_Outline.php',
        'SRFOutlineTree' => $baseDir . '/formats/outline/SRF_Outline.php',
        'SRFPageWidget' => $baseDir . '/formats/widget/SRF_PageWidget.php',
        'SRFParserFunctions' => $baseDir . '/SemanticResultFormats.parser.php',
        'SRFPloticus' => $baseDir . '/formats/ploticus/SRF_Ploticus.php',
        'SRFPloticusVBar' => $baseDir . '/formats/ploticus/SRF_PloticusVBar.php',
        'SRFProcess' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'SRFSlideShow' => $baseDir . '/formats/slideshow/SRF_SlideShow.php',
        'SRFSlideShowApi' => $baseDir . '/formats/slideshow/SRF_SlideShowApi.php',
        'SRFSparkline' => $baseDir . '/formats/sparkline/SRF_Sparkline.php',
        'SRFTime' => $baseDir . '/formats/time/SRF_Time.php',
        'SRFTimeline' => $baseDir . '/formats/timeline/SRF_Timeline.php',
        'SRFTimeseries' => $baseDir . '/formats/timeseries/SRF_Timeseries.php',
        'SRFTree' => $baseDir . '/formats/tree/SRF_Tree.php',
        'SRFTreeElement' => $baseDir . '/formats/tree/SRF_Tree.php',
        'SRFUtils' => $baseDir . '/SemanticResultFormats.utils.php',
        'SRFValueRank' => $baseDir . '/formats/valuerank/SRF_ValueRank.php',
        'SRF\\DataTables' => $baseDir . '/formats/datatables/DataTables.php',
        'SRF\\EventCalendar' => $baseDir . '/formats/calendar/EventCalendar.php',
        'SRF\\Gallery' => $baseDir . '/formats/gallery/Gallery.php',
        'SRF\\MediaPlayer' => $baseDir . '/formats/media/MediaPlayer.php',
        'SRF\\SRFExcel' => $baseDir . '/formats/excel/SRF_Excel.php',
        'SRF\\TagCloud' => $baseDir . '/formats/tagcloud/TagCloud.php',
        'SRF_FF_Distance' => $baseDir . '/formats/Filtered/filters/SRF_FF_Distance.php',
        'SRF_FF_Value' => $baseDir . '/formats/Filtered/filters/SRF_FF_Value.php',
        'SRF_FV_Calendar' => $baseDir . '/formats/Filtered/views/SRF_FV_Calendar.php',
        'SRF_FV_List' => $baseDir . '/formats/Filtered/views/SRF_FV_List.php',
        'SRF_FV_Table' => $baseDir . '/formats/Filtered/views/SRF_FV_Table.php',
        'SRF_Filtered_Filter' => $baseDir . '/formats/Filtered/filters/SRF_Filtered_Filter.php',
        'SRF_Filtered_Item' => $baseDir . '/formats/Filtered/SRF_Filtered_Item.php',
        'SRF_Filtered_View' => $baseDir . '/formats/Filtered/views/SRF_Filtered_View.php',
        'SRFiCalendar' => $baseDir . '/formats/icalendar/SRF_iCalendar.php',
        'SRFjqPlot' => $baseDir . '/formats/jqplot/SRF_jqPlot.php',
        'SRFjqPlotChart' => $baseDir . '/formats/jqplot/SRF_jqPlotChart.php',
        'SRFjqPlotSeries' => $baseDir . '/formats/jqplot/SRF_jqPlotSeries.php',
        'SRFvCard' => $baseDir . '/formats/vcard/SRF_vCard.php',
        'SRFvCardAddress' => $baseDir . '/formats/vcard/SRF_vCard.php',
        'SRFvCardEmail' => $baseDir . '/formats/vcard/SRF_vCard.php',
        'SRFvCardEntry' => $baseDir . '/formats/vcard/SRF_vCard.php',
        'SRFvCardTel' => $baseDir . '/formats/vcard/SRF_vCard.php',
        'SequentialEdge' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'SplitConditionalOrEdge' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'SplitEdge' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'SplitExclusiveOrEdge' => $baseDir . '/formats/graphviz/SRF_Process.php',
        'SplitParallelEdge' => $baseDir . '/formats/graphviz/SRF_Process.php',
    );
    $smwgQMaxSize = 128;
    $smwgQMaxDepth = 16;
    $smwgEnabledEditPageHelp = false;
    $smwgEnabledQueryDependencyLinksStore = true;
    $smwgEnabledHttpDeferredJobRequest = false;
    \SMW\ResultPrinter::$maxRecursionDepth = 15;
    $wgExtensionFunctions[] = 'autoEnableSemantics';
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
$wgExtraSignatureNamespaces = array_keys($wgNamespacesWithSubpages);

# TemplatedPageList
require_once($IP.'/extensions/TemplatedPageList/TemplatedPageList.php');
$egSubpagelistAjaxDisableRE = '#^Блог:[^/]*$#s';

$wgMaxFilenameLength = 50;
$wgGalleryOptions['captionLength'] = 50; // 1.18

$wgUseImageMagick = false;
$wgGDAlwaysResample = true;
$wgSVGConverter = 'rsvg';

require_once($IP.'/includes/GlobalFunctions.php');
if (wfIsWindows())
{
    $wgSVGConverterPath = realpath($IP."/../../app/rsvg/bin/");
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
$wgDebugDumpSqlLength = 0;

$wgDefaultSkin = 'vector';

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
$wgGroupPermissions['user']['deletedhistory'] = true;
$wgGroupPermissions['user']['undelete'] = true;
$wgGroupPermissions['user']['deletedhistory'] = true;
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

// Do not spawn a lot more apache processes
$wgRunJobsAsync = false;

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
