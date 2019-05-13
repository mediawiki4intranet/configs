#!/usr/bin/php
<?php

$help = "MW4Intranet file export helper for stock MediaWikis
(c) 2011+, Vitaliy Filippov
USAGE:
  php $argv[0] [OPTIONS] [pages...]

  This scripts downloads a standard export file from a stock MediaWiki,
  checks if there are any pages from File: namespace, and if yes, downloads
  data of these files and adds it to the export file, which is converted
  to a multipart/related one, suitable for import into MediaWiki4Intranet.

  See http://wiki.4intra.net/MW_Import_Export for the description of
  file import/export MediaWiki patch.
VERSION:
  2019-05-14
  PHP 5 is required
  curl-env-proxy.php is required to use proxy environment variables
  (http_proxy, http_no_proxy)
OPTIONS:
  -u <mediawiki_url>  : (mandatory) specify URL of MediaWiki to export pages from
    or --url <mediawiki_url>
  -o <file>           : (mandatory) write export data into <file>
    or --output <file>
  -f <page_list_file> : file with the list of pages to export, one per a line
    or --file <page_list_file>
  -t or --templates   : include templates
  -c or --curonly     : include only current revisions instead of all
  -h or --help        : display this text and exit
Either -f <page_list_file> or at least one page must be specified on commandline.
<page_list_file> can be '-', which is treated as STDIN.
<file> equal to '-' is treated as STDOUT.";

setlocale(LC_NUMERIC, "C");

$pages = array();
$listfiles = array();
$options = array();
$output = '';
for ($i = 1; $i < count($argv); $i++)
{
    $a = $argv[$i];
    if ($a{0} != '-')
        $pages[$a] = true;
    elseif ($a == '-h' || $a == '--help')
        err($help);
    elseif ($a == '--url' || $a == '-u')
    {
        $url = $argv[++$i];
        $url = preg_replace('#/*index\.php/*$#is', '', $url);
    }
    elseif ($a == '--file' || $a == '-f')
        $listfiles[] = $argv[++$i];
    elseif ($a == '--templates' || $a == '-t')
        $options['templates'] = 1;
    elseif ($a == '--curonly' || $a == '-c')
        $options['curonly'] = 1;
    elseif ($a == '--output' || $a == '-o')
        $output = $argv[++$i];
    else
        msg("Unknown option '$a' ignored.");
}

if (!$url)
    err("Source MediaWiki URL must be specified (-u <URL>).");
if (!$output)
    err("Output file or '-' must be specified (-o <FILE> or -o -).");

foreach ($listfiles as $f)
{
    if ($f != '-')
    {
        if (!($p = fopen($f, "rb")))
        {
            msg("File '$f' is not readable.");
            continue;
        }
        $f = $p;
    }
    else
        $f = STDIN;
    foreach (explode("\n", stream_get_contents($f)) as $line)
        if ($line = trim($line))
            $pages[$line] = true;
    if ($f != STDIN)
        fclose($f);
}
$pages = array_keys($pages);

if (!$pages)
    err("At least one page must be specified, either at commandline or in a page list file (-f <file>).");

if ($output != '-')
{
    $outfd = fopen($output, 'wb');
    if (!$outfd)
        err("Can not write into output file '$output'.");
}
else
    $outfd = STDOUT;

if (file_exists(dirname(__FILE__).'/curl-env-proxy.php'))
    require(dirname(__FILE__).'/curl-env-proxy.php');

$start_ts = msgw("Downloading stock export file...");
$text = curlrun(array(
    CURLOPT_URL => "$url/index.php?title=Special:Export",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $options+array(
        'pages' => implode("\n", $pages),
        'wpDownload' => 1,
        'action' => 'submit',
    ),
    CURLOPT_RETURNTRANSFER => true,
));
done(strlen($text), $start_ts);
if ($text{strlen($text)-1} != "\n")
    $text .= "\n";

if (!preg_match('#<namespace[^<>]*key="6"[^<>]*>([^<]+)</namespace>#is', $text, $m))
    err("Invalid export file: no File namespace defined.");
$NS_FILE = $m[1];

$uploads = array();
$contents = '';
$multipart = false;
while (($pos = strpos($text, '<page')) !== false)
{
    if ($pos)
    {
        $contents .= substr($text, 0, $pos);
        $text = substr($text, $pos);
    }
    $pos = strpos($text, '</page>');
    if ($pos === false)
        err("Invalid export file: <page> is not closed.");
    $page = substr($text, 0, $pos+7);
    $text = substr($text, $pos+7);
    if (($pos = strpos($page, '<title>'.$NS_FILE.':')) !== false)
    {
        $pos = $pos+8+strlen($NS_FILE);
        $pos2 = strpos($page, '</title>', $pos);
        $file = substr($page, $pos, $pos2-$pos);
        if (!$multipart)
        {
            $multipart = '--'.time();
            $contents = "Content-Type: multipart/related; boundary=$multipart
$multipart
Content-Type: text/xml
Content-ID: Revisions

$contents";
        }
        $ts = msgw("Found file: $file. Downloading file page...");
        $filepage = curlrun("$url/index.php?title=File:".urlencode($file));
        done(strlen($filepage), $ts);
        if (preg_match('#<table[^<>]*class=[\'"]?[^\'"]*filehistory.*?<a[^<>]*href=[\'"]?([^\'"<>\s]+)#is', $filepage, $m))
        {
            $fileurl = htmlspecialchars_decode($m[1]);
            if ($fileurl{0} == '/')
            {
                $pos = strpos($url, '://')+3;
                $pos = strpos($url, '/', $pos);
                $fileurl = ($pos ? substr($url, 0, $pos) : $url) . $fileurl;
            }
            $ts = msgw("Found file url: $fileurl\nDownloading file...");
            $tmpfp = tmpfile();
            $status = curlrun(array(
                CURLOPT_URL => $fileurl,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_FILE => $tmpfp,
            ));
            fseek($tmpfp, 0, 2);
            $size = ftell($tmpfp);
            done($size, $ts);
            $sha1 = sha1_stream($tmpfp);
            $page = substr($page, 0, -7); // remove </page>
            preg_match_all('#<revision[^<>]*>.*?</revision>#is', $page, $revisions, PREG_PATTERN_ORDER);
            $max_ts = '';
            $max_author = '';
            foreach ($revisions[0] as $r)
            {
                preg_match('#<timestamp>([^<]*)</timestamp>#is', $r, $m);
                if ($m[1] > $max_ts)
                {
                    $max_ts = $m[1];
                    preg_match('#<contributor.*?</contributor>#is', $r, $m);
                    $max_author = $m[0];
                }
            }
            $filename = substr($fileurl, strrpos($fileurl, '/')+1);
            $page .= "<upload><timestamp>$max_ts</timestamp>$max_author".
                "<comment /><filename>".htmlspecialchars($filename)."</filename>".
                "<src sha1=\"$sha1\">multipart://".htmlspecialchars($filename)."</src>".
                "<size>$size</size></upload></page>";
            $uploads[] = array(
                'fp'    => $tmpfp,
                'title' => $file,
                'url'   => $fileurl,
                'name'  => $filename,
                'size'  => $size,
                'sha1'  => $sha1,
            );
        }
    }
    $contents .= $page;
}
$contents .= $text;
fwrite($outfd, $contents);
foreach($uploads as $f)
{
    fwrite($outfd, "$multipart
Content-Type: application/binary
Content-Transfer-Encoding: Little-Endian
Content-ID: $f[name]
Content-Length: $f[size]

");
    fseek($f['fp'], 0, 0);
    stream_copy_to_stream($f['fp'], $outfd);
    fclose($f['fp']);
}
done(ftell($outfd), $start_ts, "Done!");
exit;

function err($text)
{
    global $argv;
    fwrite(STDERR, $text." See usage with php $argv[0] --help.\n");
    exit(-1);
}

function msg($text)
{
    fwrite(STDERR, $text."\n");
}

function msgw($text)
{
    fwrite(STDERR, $text);
    fflush(STDERR);
    return microtime(true);
}

function done($size, $ts, $pre = '')
{
    $ts = microtime(true)-$ts;
    $hsize = $size > 0x100000 ? sprintf("%.2f Mb", $size/0x100000) :
        ($size > 0x400 ? sprintf("%.2f Kb", $size/0x400) : "$size bytes");
    fprintf(STDERR, "$pre $hsize in %.2f seconds = %d Kb/s\n", $ts, $size/1000/$ts);
}

function curlrun($options)
{
    if (!is_array($options))
        $options = array(
            CURLOPT_URL => $options,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
        );
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    if (class_exists('CurlEnvProxy'))
        CurlEnvProxy::set($ch, $options[CURLOPT_URL]);
    return curl_exec($ch);
}

function sha1_stream($fp)
{
    fseek($fp, 0, 0);
    $ctx = hash_init('sha1');
    hash_update_stream($ctx, $fp);
    $hash = hash_final($ctx);
    $hash = wfBaseConvert($hash, 16, 36, 31);
    return $hash;
}

/**
 * Verbatim copy from includes/GlobalFunctions.php
 * -----------------------------------------------
 *
 * Convert an arbitrarily-long digit string from one numeric base
 * to another, optionally zero-padding to a minimum column width.
 *
 * Supports base 2 through 36; digit values 10-36 are represented
 * as lowercase letters a-z. Input is case-insensitive.
 *
 * @param $input String: of digits
 * @param $sourceBase Integer: 2-36
 * @param $destBase Integer: 2-36
 * @param $pad Integer: 1 or greater
 * @param $lowercase Boolean
 * @return String or false on invalid input
 */
function wfBaseConvert( $input, $sourceBase, $destBase, $pad=1, $lowercase=true ) {
	$input = strval( $input );
	if( $sourceBase < 2 ||
		$sourceBase > 36 ||
		$destBase < 2 ||
		$destBase > 36 ||
		$pad < 1 ||
		$sourceBase != intval( $sourceBase ) ||
		$destBase != intval( $destBase ) ||
		$pad != intval( $pad ) ||
		!is_string( $input ) ||
		$input == '' ) {
		return false;
	}
	$digitChars = ( $lowercase ) ?  '0123456789abcdefghijklmnopqrstuvwxyz' : '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$inDigits = array();
	$outChars = '';

	// Decode and validate input string
	$input = strtolower( $input );
	for( $i = 0; $i < strlen( $input ); $i++ ) {
		$n = strpos( $digitChars, $input{$i} );
		if( $n === false || $n > $sourceBase ) {
			return false;
		}
		$inDigits[] = $n;
	}

	// Iterate over the input, modulo-ing out an output digit
	// at a time until input is gone.
	while( count( $inDigits ) ) {
		$work = 0;
		$workDigits = array();

		// Long division...
		foreach( $inDigits as $digit ) {
			$work *= $sourceBase;
			$work += $digit;

			if( $work < $destBase ) {
				// Gonna need to pull another digit.
				if( count( $workDigits ) ) {
					// Avoid zero-padding; this lets us find
					// the end of the input very easily when
					// length drops to zero.
					$workDigits[] = 0;
				}
			} else {
				// Finally! Actual division!
				$workDigits[] = intval( $work / $destBase );

				// Isn't it annoying that most programming languages
				// don't have a single divide-and-remainder operator,
				// even though the CPU implements it that way?
				$work = $work % $destBase;
			}
		}

		// All that division leaves us with a remainder,
		// which is conveniently our next output digit.
		$outChars .= $digitChars[$work];

		// And we continue!
		$inDigits = $workDigits;
	}

	while( strlen( $outChars ) < $pad ) {
		$outChars .= '0';
	}

	return strrev( $outChars );
}
