<?php

/**
 * This tool is a part of MediaWiki4Intranet Import-Export patch.
 * http://wiki.4intra.net/MW_Import&Export
 * http://wiki.4intra.net/Mediawiki4Intranet
 * Copyright (c) 2010+, Vitaliy Filippov
 *
 * Maintenance tool updating archived image revision filenames to:
 * 1) match the revision date, not the NEXT revision date as in the
 *    original MediaWiki.
 * 2) if $wgTransliterateUploadFilenames is true and the
 *    'translit-upload-filenames' patch is active, then change
 *    cyrillic uploaded file names into transliterated ones.
 *
 * USAGE: copy this file into maintenance/ subdirectory of MediaWiki
 * installation and run with command "php file-upload-renamer.php"
 *
 * DO NOT run this if you don't plan to use MW4Intranet Import-Export patch.
 */

$IP = '../../';
require_once( dirname( __FILE__ ) . '/../../maintenance/Maintenance.php' );

class OldImageRenamer extends Maintenance {

	var $quiet;
	var $remove_unexisting;
	var $bak;
	var $mDescription = 'This script does 2 things:

1) By default, old files in MediaWiki are stored containing timestamp of **next**
revision in file names. This script renames them to contain **their own** timestamp
in file names (plus a \'T\' letter in the beginning to avoid collisions with old scheme).

2) With MediaWiki4Intranet patch, if $wgTransliterateUploadFilenames is true, all upload file
names are transliterated during upload. So MediaWiki expects that filenames of
already uploaded files are also transliterated. This script automatically checks
them and transliterates if needed.
';

	function __construct() {
		$this->addOption( 'quiet', 'Silent mode', false, false );
		$this->addOption( 'delunexisting', 'Remove archive images non-existing on FS anymore', false, false );
		$this->addOption( 'backup', 'Make backups', false, false );
	}

	function out($s) {
		if ( !$this->quiet ) {
			print $s;
		}
	}

	function execute() {
		$this->quiet = $this->getOption( 'quiet', false );
		$this->remove_unexisting = $this->getOption( 'delunexisting', false );
		$this->bak = $this->getOption( 'backup', false );
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select( 'oldimage', '*', '1', __METHOD__, array( 'FOR UPDATE', 'ORDER BY' => 'oi_name, oi_timestamp' ) );
		$file = NULL;
		$lastfilename = NULL;
		$getPhys = method_exists( 'File', 'getPhys' ) ? 'getPhys' : 'getName';
		while ( $oi = $dbr->fetchRow( $res ) ) {
			$row = array();
			foreach ( $oi as $k => $v ) {
				if ( !is_numeric( $k ) ) {
					$row[$k] = $v;
				}
			}
			$ts = wfTimestamp( TS_MW, $oi[ 'oi_timestamp' ] );
			$fn = $oi[ 'oi_archive_name' ];
			if ( ( $p = strpos( $fn, '!' ) ) !== false ) {
				if ( !$file || $lastfilename != $oi['oi_name'] ) {
					$lastfilename = $oi['oi_name'];
					$file = wfLocalFile( $oi['oi_name'] );
					$path = $file->repo->getZonePath( 'public' ) . '/archive/' . $file->getHashPath();
				}
				$nfn = 'T'.$ts.'!'.$file->$getPhys();
				if ( $fn != $nfn ) {
					if ( $this->remove_unexisting && !file_exists( $path . $fn ) ) {
						$dbr->delete( 'oldimage', $row, __METHOD__ );
						print "Removed $fn from oldimage table\n";
						continue;
					}
					if ( file_exists( $path . $nfn ) ) {
						if ( $this->bak ) {
							$i = 0;
							while ( file_exists( $path.$nfn.'.'.$i ) ) {
								$i++;
							}
							rename( $path.$nfn, $path.$nfn.'.'.$i );
							print "WARNING: moved $path$nfn into $path$nfn.$i\n";
						} else {
							print "Error moving $path$fn to $path$nfn: $path$nfn already exists\n";
							break;
						}
					}
					if ( rename( $path . $fn, $path . $nfn ) ) {
						if ( $dbr->update( 'oldimage', array( 'oi_archive_name' => $nfn ), $row, __METHOD__ ) ) {
							$this->out( "Moved $path$fn to $path$nfn\n" );
						} else {
							rename($path . $nfn, $path . $fn);
							print "Error moving $path$fn to $path$nfn: can't update $fn to $nfn in the database\n";
							break;
						}
					} else {
						print "Error moving file :-(\n";
						break;
					}
				}
			}
		}
		// Transliterate existing upload file names (MediaWiki4Intranet patch)
		global $wgTransliterateUploadFilenames;
		if ( $wgTransliterateUploadFilenames && $getPhys == 'getPhys' ) {
			$res = $dbr->select( 'image', 'img_name', '1', __METHOD__, array( 'FOR UPDATE' ) );
			foreach ( $res as $img ) {
				$file = wfLocalFile( $img->img_name );
				$path = $file->repo->getZonePath( 'public' ) . $file->getHashPath();
				$name = $file->getName();
				$phys = $file->getPhys();
				if ( $name != $phys && file_exists( $path.$name ) && !file_exists( $path.$phys ) ) {
					if ( rename( $path.$name, $path.$phys ) ) {
						$this->out("Renamed $path$name to $path$phys\n");
					} else {
						print "Error moving $path$name to $path$phys\n";
					}
				}
			}
		}
		$dbr->commit();
	}
}

$maintClass = "OldImageRenamer";
require_once( RUN_MAINTENANCE_IF_MAIN );
