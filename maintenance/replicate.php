#!/usr/bin/php
<?php
/**
 * TODO maybe use API? But this will add a dependency to JSON/XML/whatever.
 *
 * A script for MediaWiki4Intranet page replication, with support for:
 * - Automatic replication of templates and images used in the articles
 * - Incremental replication
 * REQUIRES modified MediaWiki import/export mechanism, see MediaWiki4Intranet patch:
 * http://wiki.4intra.net/MW_Import_Export
 *
 * Скрипт для репликации вики-страниц между разными MediaWiki, поддерживает:
 * - Автоматическую репликацию шаблонов и изображений, использованных в статьях
 * - Инкрементальную репликацию
 * ТРЕБУЕТ модифицированного механизма импорта/экспорта MediaWiki, см. патч MediaWiki4Intranet:
 * http://wiki.4intra.net/MW_Import_Export
 */

$HELP_TEXT = <<<EOF
MediaWiki4Intranet replication script
Copyright (c) 2010+ Vitaliy Filippov <vitalif\@mail.ru>

USAGE: php $argv[0] [OPTIONS] <replication-config.ini> [targets...]

OPTIONS:

-t HOURS
  only select pages which were changed during last HOURS hours.
  I.e. if the replication script is ran each day, you can specify -t 24 to
  export only pages changed since last run, or better -t 25 to allow some
  overlap with previous day and make replication more reliable.

-t 'YYYY-MM-DD[ HH:MM:SS]'
  same as above, but specify date/time, not the relative period in hours.

-i
  when using regular incremental replication (-t option), the following
  situation may be possible:
  * template was created, say, on 2011-10-11
  * it is outside replication category, therefore does not replicate by itself
    (-t 24 is used each day)
  * article was created, say, on 2011-10-13, in replication category
  * so article replicates 2011-10-14, but the template does not
    (because it is not modified during last 24 hours)
  This replication script by default ignores last modification date for
  templates and images, so the described situation is impossible, but you
  can change this behaviour using this -i option.

When called without target list, $argv[0] will attempt to replicate all targets
found in config file. There must be 2 sections in config file according to
each target and named "<Target>SourceWiki" and "<Target>DestinationWiki".

Config file fragment syntax (Replace __Test__ with desired [target] name):

[__Test__SourceWiki]
URL=<source wiki url>
Category=<source category name for selecting pages>
CategoryWithClosure=<source category name for selecting pages including its subcategories>
NotCategory=<source category name for replication denial>
RemoveConfidential=<'yes' or 'no' (default)>
FullHistory=<'yes' or 'no' (default), 'yes' replicates all page revisions, not only the last one>
BasicLogin=<HTTP basic auth username, if needed>
BasicPassword=<HTTP basic auth password, if needed>

[__Test__DestinationWiki]
URL=<destination wiki url>
BasicLogin=<HTTP basic auth username, if needed>
BasicPassword=<HTTP basic auth password, if needed>
User=<name of a user having import rights in destination wiki>
Password=<his password>

EOF;

// For PHP < 5.2.1
if (!function_exists('sys_get_temp_dir'))
{
    function sys_get_temp_dir()
    {
        if ($temp = getenv('TMP'))
        {
            return $temp;
        }
        if ($temp = getenv('TEMP'))
        {
            return $temp;
        }
        if ($temp = getenv('TMPDIR'))
        {
            return $temp;
        }
        $temp = tempnam(__FILE__, '');
        if (file_exists($temp))
        {
            unlink($temp);
            return dirname($temp);
        }
        return null;
    }
}

chdir(dirname(__FILE__));
error_reporting(E_ALL | E_STRICT);
$BUFSIZE = 0x10000;

replicator();
exit;

// Main function
function replicator()
{
    global $ignore_since_images, $since_time, $cookieJar,
        $CurrentTarget, $LastRequestDescription, $HELP_TEXT, $argv;
    $ignore_since_images = true;
    $since_time = false;
    $targets = array();
    $config_file = NULL;

    for ($i = 1; $i < count($argv); $i++)
    {
        if ($argv[$i] == '-t')
        {
            $since_time = $argv[++$i];
            if (!preg_match('/^\s*(\d{4,}-\d{2}-\d{2}(?:\s+\d{2}:\d{2}:\d{2})?)\s*$/s', $since_time, $m))
            {
                $since_time = intval(time() - 3600*$since_time);
                $since_time = date("Y-m-d H:i:s", $since_time);
            }
            else
            {
                $since_time = $m[1];
            }
        }
        elseif ($argv[$i] == '-i')
        {
            $ignore_since_images = false;
        }
        elseif ($config_file === NULL)
        {
            $config_file = $argv[$i];
        }
        else
        {
            $targets[] = $argv[$i];
        }
    }

    $config = $config_file ? read_config($config_file) : NULL;
    if (!$config)
    {
        fwrite(STDERR, $HELP_TEXT);
        exit;
    }

    ob_implicit_flush(TRUE);
    $cookieJar = dirname(__FILE__)."/cookiejar.txt";

    if (!$targets)
    {
        $targets = array_keys($config);
    }
    foreach ($targets as $t)
    {
        $CurrentTarget = $t;
        repl_log("Begin replication");
        try
        {
            replicate($config[$t]['src'], $config[$t]['dest']);
        }
        catch (Exception $e)
        {
            repl_log("$e");
            repl_log("Last request was: $LastRequestDescription");
        }
    }
}

// Log something with current target and timestamp
function repl_log($s)
{
    global $CurrentTarget;
    echo date('[Y-m-d H:i:s]') . " [$CurrentTarget] $s\n";
}

// Get relative url from wiki $wiki
function GET($wiki, $url)
{
    global $cookieJar, $LastRequestDescription;
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $wiki['url'].$url,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
    ));
    if (!empty($wiki['basiclogin']) && !empty($wiki['basicpassword']))
    {
        curl_setopt($curl, CURLOPT_USERPWD, $wiki['basiclogin'].':'.$wiki['basicpassword']);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $content = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $LastRequestDescription = "GET $wiki[url]$url = HTTP $status";
    curl_close($curl);
    return array($status, $content);
}

// Post relative url to wiki $wiki
function POST($wiki, $url, $params, $filename = NULL)
{
    global $cookieJar, $LastRequestDescription;
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $wiki['url'].$url,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
    ));
    if ($filename === NULL)
    {
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    }
    else
    {
        $fp = fopen($filename, 'wb');
        curl_setopt($curl, CURLOPT_FILE, $fp);
    }
    $content = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $LastRequestDescription = "POST $wiki[url]$url = HTTP $status, parameters:\n".var_export($params, true);
    curl_close($curl);
    if ($filename !== NULL)
    {
        fclose($fp);
        return $status;
    }
    return array($status, $content);
}

// Login into wiki described by $params
function login_into($params, $desc)
{
    if (empty($params['user']) || empty($params['password']))
    {
        return '';
    }
    list($status, $content) = GET($params, '/index.php?title=Special:UserLogin');
    if ($status != 200)
    {
        throw new ReplicateException("Invalid response status");
    }
    if (!preg_match('/<input[^<>]*name="wpLoginToken"[^<>]*value="([^"]+)"[^<>]*>/s', $content, $m))
    {
        throw new ReplicateException("No input name=wpLoginToken found");
    }
    $token = $m[1];
    list($status, $content) = POST(
        $params, '/index.php?title=Special:UserLogin&action=submitlogin&type=login',
        array(
            'wpName' => $params['user'],
            'wpPassword' => $params['password'],
            'wpLoginAttempt' => 1,
            'wpLoginToken' => $token,
        )
    );
    if ($status != 302)
    {
        throw new ReplicateException("Incorrect login (no redirection, status=$status)");
    }
}

// Generate export page list from wiki $wiki using $params and $desc as error description
function page_list_load($wiki, $params)
{
    $params['addcat'] = 'Add';
    list($status, $content) = POST($wiki, "/index.php?title=Special:Export&action=submit", $params);
    if ($status != 200)
    {
        throw new ReplicateException("Invalid response status");
    }
    preg_match('#<textarea[^<>]*>([^<]*)</textarea>#is', $content, $m);
    return trim($m[1]);
}

// Retrieve list of Wiki pages from category $cat,
// NOT in category $notcat, with all used images and
// templates by default, but only modified after $modifydate
function page_list($src, $modifydate = '', $ignore_since_images = false)
{
    $ignore_since_images = $ignore_since_images && $modifydate !== '';
    $desc = '';
    if (!empty($src['categorywithclosure']))
    {
        $desc .= "Category:".$src['categorywithclosure']." including all subcategories";
    }
    if (!empty($src['category']))
    {
        if ($desc)
        {
            $desc .= " plus ";
        }
        $desc .= "Category:".$src['category'];
    }
    if (!empty($src['notcategory']))
    {
        $desc .= ", excluding Category:".$src['notcategory'];
    }
    if ($ignore_since_images)
    {
        $desc .= ", with all used images/templates";
    }
    if (!empty($modifydate))
    {
        $desc .= ", modified after $modifydate";
    }
    if (!$ignore_since_images)
    {
        $desc .= ", with all used images/templates";
    }
    repl_log("Retrieving $desc");
    try
    {
        $common = array(
            'modifydate' => $modifydate,
            'notcategory' => @$src['notcategory'],
        );
        if (!$ignore_since_images)
        {
            $common['templates'] = $common['images'] = $common['redirects'] = 1;
        }
        $pages = '';
        if (!empty($src['category']))
        {
            $params = $common + array(
                'pages' => $pages,
                'catname' => $src['category'],
            );
            $pages = page_list_load($src, $params);
        }
        if (!empty($src['categorywithclosure']))
        {
            $params = $common + array(
                'pages' => $pages,
                'catname' => $src['categorywithclosure'],
                'closure' => 1,
            );
            $pages = page_list_load($src, $params);
        }
        if ($pages && $ignore_since_images)
        {
            // Add templates, images and redirects in a separate request, without passing modifydate
            $pages = page_list_load($src, array(
                'pages' => $pages,
                'notcategory' => @$src['notcategory'],
                'templates' => 1,
                'images' => 1,
                'redirects' => 1,
            ));
        }
    }
    catch (Exception $e)
    {
        throw new ReplicateException("Page list loading failed: $e");
    }
    return $pages;
}

function replicate($src, $dest)
{
    global $since_time, $ignore_since_images;
    // Login into source wiki
    login_into($src, 'source wiki');
    // Read page list for replication
    $text = page_list($src, $since_time, $ignore_since_images);
    if (!$text)
    {
        throw new ReplicateException("No pages need replication in source wiki");
    }
    repl_log(substr_count($text, "\n")." pages listed");
    $ts = microtime(true);
    // Read export XML / multipart file
    $fn = tempnam(sys_get_temp_dir(), 'imp');
    $params = array(
        'images' => 1,
        'selfcontained' => 1,
        'wpDownload' => 1,
        'pages' => $text,
        'curonly' => 1,
    );
    if (empty($src['removeconfidential']))
    {
        $params['confidential'] = true;
    }
    if (empty($src['fullhistory']))
    {
        $params['curonly'] = true;
    }
    $status = POST($src, "/index.php?title=Special:Export&action=submit", $params, $fn);
    if ($status != 200)
    {
        throw new ReplicateException("Could not retrieve export archive");
    }
    $tx = microtime(true);
    repl_log(sprintf("Retrieved %d bytes in %.2f seconds", filesize($fn), $tx-$ts));
    // Login into destination wiki
    login_into($dest, 'destination wiki');
    // Retrieve token for importing
    list($status, $text) = GET($dest, "/index.php?title=Special:Import");
    if ($status != 200)
    {
        throw new ReplicateException("Could not retrieve Special:Import page");
    }
    preg_match('/<input([^<>]*name="editToken"[^<>]*)>/is', $text, $m);
    if (!$m)
    {
        $text = preg_replace('/^.*<!-- start content -->(.*)<!-- end content -->.*$/is', '\1', $text);
        throw new ReplicateException("No editToken on Special:Import. Content was:\n".trim($text));
    }
    preg_match('/value=\"([^\"]*)\"/is', $m[1], $m);
    $token = $m[1];
    // Run import
    list($status, $text) = POST($dest, "/index.php?title=Special:Import&action=submit", array(
        'source' => 'upload',
        'editToken' => $token,
        'xmlimport' => '@'.$fn,
    ));
    if ($status != 200)
    {
        throw new ReplicateException("Could not import");
    }
    if (preg_match('/<p[^<>]*class\s*=\s*[\"\']?error[^<>]*>\s*(.*?)\s*<\/p\s*>/is', $text, $m))
    {
        throw new ReplicateException("Could not import: $m[1]");
    }
    $tp = microtime(true);
    repl_log(sprintf("Imported in %.2f seconds", $tp-$tx));
    // Extract the import report
    $report = '';
    if (preg_match('/<!--\s*start\s*content\s*-->.*?<ul>/is', $text, $m, PREG_OFFSET_CAPTURE))
    {
        $report = substr($text, $m[0][1]+strlen($m[0][0]));
        if (($p = stripos($report, '</ul')) !== false)
        {
            $report = substr($report, 0, $p);
        }
        $report = str_replace('&nbsp;', ' ', $report);
        $report = preg_replace('/\s+/', ' ', $report);
        $report = preg_replace('/<li[^<>]*>/', "\n", $report);
        $report = preg_replace('/<\/?[a-z0-9_:\-]+(\/?\s+[^<>]*)?>/', '', $report);
        $report = trim(html_entity_decode($report));
    }
    if ($report === '')
    {
        throw new ReplicateException("Could not replicate, no import report found in response content:\n$text");
    }
    repl_log("Report:\n$report");
    unlink($fn);
}

// Read ini-like config file from $file
// [XXX(Source|Destination)Wiki]
// key = value
// ...
function read_config($file)
{
    $fp = fopen($file, 'r');
    if (!$fp)
    {
        return NULL;
    }
    $cfg = array();
    $current = &$cfg;
    $is_full = array();
    while ($s = fgets($fp))
    {
        $s = trim($s);
        $s = preg_replace('/(^|\s+)#.*$/s', '', $s);
        if (!$s)
        {
            continue;
        }
        if (preg_match('/^\s*\[([^\]]*)(Source|Destination)Wiki\]\s*$/is', $s, $m))
        {
            $target = strtolower($m[1]);
            $key = strtolower($m[2]) == 'source' ? 'src' : 'dest';
            if (!empty($cfg[$target][$key == 'src' ? 'dest' : 'src']))
            {
                $is_full[$target] = true;
            }
            if (empty($cfg[$target][$key]))
            {
                $cfg[$target][$key] = array();
            }
            $current = &$cfg[$target][$key];
        }
        elseif (preg_match('/^\s*([^=]*[^\s=])\s*=\s*(.*)/is', $s, $m))
        {
            $k = strtolower($m[1]);
            $v = $m[2];
            if ($k == 'url')
            {
                $v = rtrim($v, '/');
            }
            elseif ($k == 'fullhistory' || $k == 'removeconfidential')
            {
                $v = strtolower($v);
                $v = $v == 'yes' || $v == 'true' || $v == 'on' || $v == '1';
            }
            $current[$k] = $v;
        }
    }
    fclose($fp);
    foreach ($cfg as $k => $v)
    {
        if (!$is_full[$k])
        {
            unset($cfg[$k]);
        }
    }
    return $cfg;
}

class ReplicateException extends Exception
{
    function __toString()
    {
        return $this->getMessage();
    }
}
