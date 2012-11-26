<?php

// A simple script: writes SQL code to change user IDs in one Wiki database
// so that they will be equal with another Wiki's database user IDs.
// Then you should be capable to use $wgSharedTables[] = 'user'.
// (c) Vitaliy Filippov, 2011

// Database configuration

if (count($argv) < 8)
    fwrite(STDERR, "User ID change script
USAGE: php $argv[0] <mysql_server> <db1> <user1> <pass1> <db2> <user2> <pass2>
mysql_server is 'address[:port]' or '/path/to/unix/socket' for MySQL
db1, user1, pass1 specify target database (database in which user IDs must be changed)
db2, user2, pass2 specify reference database (database WITH which user table will be shared)
");

$mysql_server = $argv[1];
$source_db = $argv[2];
$source_dbuser = $argv[3];
$source_dbpass = $argv[4];
$reference_db = $argv[5];
$reference_dbuser = $argv[6];
$reference_dbpass = $argv[7];

// Mapping onfiguration

$shared_tables = array('user', 'user_groups', 'mwq_choice', 'mwq_choice_stats', 'mwq_question', 'mwq_question_test', 'mwq_test', 'mwq_ticket');

$ID_LINKS = explode("\n",
"user.user_id
external_user.eu_local_id
archive.ar_user
drafts.draft_user
filearchive.fa_deleted_user
filearchive.fa_user
image.img_user
ipblocks.ipb_user
logging.log_user
mwq_ticket.tk_user_id
oldimage.oi_user
page_last_visit.pv_user
page_restrictions.pr_user
protected_titles.pt_user
recentchanges.rc_user
revision.rev_user
user_groups.ug_user
user_newtalk.user_id
user_properties.up_user
watchlist.wl_user
wikilog_authors.wla_author
wikilog_comments.wlc_user
wikilog_subscriptions.ws_user
halo_acl_quickacl.user_id");

$links = array();
foreach ($ID_LINKS as $s)
{
    list($table, $field) = explode('.', $s, 2);
    $links[$table][$field] = 'id';
}

$links['wikilog_wikilogs']['wlw_authors'] = 'wikilog';
$links['wikilog_posts']['wlp_authors'] = 'wikilog';
$links['halo_acl_groups']['mg_users'] = 'splitid';
$links['halo_acl_rights']['users'] = 'splitid';
$links['halo_acl_security_descriptors']['mr_users'] = 'splitid';
$links['halo_acl_group_members']['child_id'] = 'child_type==user';

foreach (explode("\n",
"user.user_name
archive.ar_user_text
filearchive.fa_user_text
image.img_user_text
logging.log_user_text
oldimage.oi_user_text
mwq_ticket.tk_user_text
recentchanges.rc_user_text
revision.rev_user_text
wikilog_authors.wla_author_text
wikilog_comments.wlc_user_text
poll_vote.poll_user") as $s)
{
    list($table, $field) = explode('.', $s, 2);
    $links[$table][$field] = 'name';
}

// End configuration

$db = mysql_connect($mysql_server, $source_dbuser, $source_dbpass);
$refdb = mysql_connect($mysql_server, $reference_dbuser, $reference_dbpass);
if (!mysql_select_db($source_db, $db) || !mysql_select_db($reference_db, $refdb))
    die("Can't connect to one of databases\n");
mysql_query('SET NAMES utf8', $db);
mysql_query('SET NAMES utf8', $refdb);

// Determine NAME=>ID / EMAIL=>ID mapping for reference database
$res = mysql_query('SELECT * FROM user', $refdb);
$refusers = $dupemail = $refuseridbyname = $refuseridbyemail = array();
while ($row = mysql_fetch_assoc($res))
{
    $refusers[$row['user_id']] = $row;
    $refuseridbyname[$row['user_name']] = $row['user_id'];
    if ($row['user_email'])
    {
        if ($refuseridbyemail[$row['user_email']])
            $dupemail[$row['user_email']]++;
        else
            $refuseridbyemail[$row['user_email']] = $row['user_id'];
    }
}
foreach ($dupemail as $email => $n)
    unset($refuseridbyemail[$email]);

// Find multiple users with one email in source db
$res = mysql_query('SELECT * FROM user', $db);
$srcusers = $dupemail = $srcuserbyemail = array();
while ($row = mysql_fetch_assoc($res))
{
    $srcusers[$row['user_id']] = $row;
    if ($row['user_email'])
    {
        if ($srcuserbyemail[$row['user_email']])
        {
            if (!$dupemail[$row['user_email']])
                $dupemail[$row['user_email']] = array($srcuserbyemail[$row['user_email']]);
            $dupemail[$row['user_email']][] = $row['user_id'];
        }
        else
            $srcuserbyemail[$row['user_email']] = $row['user_id'];
    }
}

if ($dupemail)
{
    $t = "Following users have same email address in source db:\n";
    foreach ($dupemail as $email => $ids)
    {
        $l = array();
        foreach ($ids as $id)
            $l[] = $srcusers[$id]['user_name'];
        $t .= "$email: ".implode(', ', $l)."\n";
    }
    $t .= "Continue? (Y/n) ";
    fwrite(STDERR, $t);
    if (preg_match('/^\s*n/is', fgets(STDIN)))
        exit;
    foreach ($dupemail as $email => $ids)
        unset($refuseridbyemail[$email]);
}

// Determine SRCID=>REFID mapping for users
$unknown = $useridbyid = $usernamebyname = $referenced = $merged = array();
foreach ($srcusers as $row)
{
    if ($refuseridbyname[$row['user_name']])
    {
        $useridbyid[$row['user_id']] = $refuseridbyname[$row['user_name']];
        $referenced[$refuseridbyname[$row['user_name']]] = true;
    }
}

// FIXME correct merging/removing of users
foreach ($srcusers as $row)
{
    if ($refuseridbyname[$row['user_name']])
    {
    }
    elseif ($refuseridbyemail[$row['user_email']])
    {
        $name = $refusers[$refuseridbyemail[$row['user_email']]]['user_name'];
        if ($referenced[$refuseridbyemail[$row['user_email']]])
        {
            $merged[$row['user_id']] = true;
            fwrite(STDERR, "Will merge $row[user_name] to $name\n");
        }
        else
            fwrite(STDERR, "Will rename $row[user_name] to $name\n");
        $usernamebyname[$row['user_name']] = $name;
        $useridbyid[$row['user_id']] = $refuseridbyemail[$row['user_email']];
    }
    else
        $unknown[] = $row;
}

if ($unknown)
{
    $l = array();
    $t = "Following users are not found in reference db:\n";
    foreach ($unknown as $row)
        $t .= $row['user_name'].($row['user_email'] ? '<'.$row['user_email'].'>' : '') . "\n";
    $t .= "Add them there and continue? (Y/n) ";
    fwrite(STDERR, $t);
    if (preg_match('/^\s*n/is', fgets(STDIN)))
        exit;
    foreach ($unknown as $row)
    {
        // User does not exist in reference database, add him there
        fwrite(STDERR, "Adding user $row[user_name] to $reference_db\n");
        $new = $row;
        unset($new['user_id']);
        mysql_query(
            "INSERT INTO `user` (`".implode("`,`", array_keys($new))."`) VALUES ('".
            implode("','", array_map('mysql_real_escape_string', array_values($new)))."')",
            $refdb
        );
        $useridbyid[$row['user_id']] = mysql_insert_id($refdb);
    }
}

// Print SQL which changes user ids/names
print "SET NAMES utf8;\n\n";
foreach ($links as $t => $fields)
{
    $id_field = false;
    $res = mysql_query("DESC `$t`", $db);
    while ($row = mysql_fetch_assoc($res))
        if (strpos(strtolower($row['Extra']), 'auto_increment') !== false)
            $id_field = $row['Field'];
    if ($t == 'user')
        $id_field = false;
    $res = mysql_query("SELECT * FROM `$t`", $db);
    $k = false;
    $updated = 0;
    while ($row = mysql_fetch_assoc($res))
    {
        if ($t == 'user' && $merged[$row['user_id']])
        {
            // Skip merged users
            continue;
        }
        foreach ($fields as $field => $mapping)
        {
            $new = $row[$field];
            if ($mapping == 'id')
            {
                if ($useridbyid[$row[$field]])
                    $new = $useridbyid[$row[$field]];
            }
            elseif ($mapping == 'name')
            {
                if ($usernamebyname[$row[$field]])
                    $new = $usernamebyname[$row[$field]];
            }
            elseif ($mapping == 'wikilog')
            {
                $new = array();
                foreach (unserialize($row[$field]) as $name => $id)
                {
                    if ($usernamebyname[$name])
                        $new[$usernamebyname[$name]] = $useridbyid[$id];
                    else
                        $new[$name] = $useridbyid[$id];
                }
                $new = serialize($new);
            }
            elseif ($mapping == 'splitid')
            {
                $new = explode(',', $row[$field]);
                foreach ($new as &$id)
                    if ($useridbyid[$id])
                        $id = $useridbyid[$id];
                unset($id);
                $new = implode(',', $new);
            }
            elseif ($mapping == 'child_type==user')
            {
                if ($row['child_type'] == 'user' && $useridbyid[$row[$field]])
                    $new = $useridbyid[$row[$field]];
            }
            if ($new !== $row[$field])
            {
                $row[$field] = $new;
                $updated++;
            }
        }
        if (!$k)
        {
            if (!$id_field)
                print "TRUNCATE `$t`;\n";
            print "INSERT INTO `$t` (`".implode("`,`", array_keys($row))."`) VALUES\n";
        }
        if ($k)
            print ",\n";
        print "('".implode("','", array_map('mysql_real_escape_string', array_values($row)))."')";
        $k = true;
    }
    if ($k)
    {
        if ($id_field)
        {
            print "\nON DUPLICATE KEY UPDATE ";
            $a = array();
            foreach ($fields as $field => $mapping)
                $a[] = "`$field`=VALUES(`$field`)";
            print implode(', ', $a);
        }
        print ";\n-- (will update $updated rows)\n\n";
    }
}

// Update ACLs
foreach ($usernamebyname as $from => $to)
{
    print "UPDATE page, revision, text SET old_text=REPLACE(old_text, '$from', '$to')
WHERE old_text LIKE '%$from%' AND old_id=rev_text_id AND rev_id=page_latest AND page_namespace=102;\n";
}
if ($usernamebyname)
    print "\n";

foreach ($shared_tables as $t)
    print "GRANT ALL PRIVILEGES ON `$reference_db`.`$t` TO '$source_dbuser'@'localhost';\n";
print "FLUSH PRIVILEGES;\n";
