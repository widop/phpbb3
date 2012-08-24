<?php
/**
*
* @package phpBB SEO GYM Sitemaps
* @version $Id: gym_style.php 331 2011-11-11 15:42:06Z dcz $
* @copyright (c) 2006 - 2010 www.phpbb-seo.com
* @license http://opensource.org/osi3.0/licenses/lgpl-license.php GNU Lesser General Public License
*
*/


define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
$gym_cache_path = $phpbb_root_path . 'gym_sitemaps/cache/';

// Report all errors, except notices and deprecation messages
if (!defined('E_DEPRECATED'))
{
	define('E_DEPRECATED', 8192);
}
error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);

if (version_compare(PHP_VERSION, '6.0.0-dev', '<')) {
	@set_magic_quotes_runtime(0);
}
// Load Extensions
if (!empty($load_extensions) && function_exists('dl')) {
	$load_extensions = explode(',', $load_extensions);
	foreach ($load_extensions as $extension) {
		@dl(trim($extension));
	}
}
// Option, strip the white spaces in the output, saves a bit of bandwidth.
$strip_spaces = true;
// Option, grabb phpBB stylesheet if using prosilver, will adapt the styling
$load_phpbb_css = false;
// Will automatically update the cache in case the original files are modified.
// Rss or google output
$action_expected = array('rss', 'google');
// CSS or XSLT stylsheet
$type_expected = array('css', 'xsl');

// Language
$language = (isset($_GET['lang']) && !is_array($_GET['lang'])) ? htmlspecialchars(basename((string) $_GET['lang'])) : '';
$action = isset($_GET['action']) && @in_array($_GET['action'], $action_expected) ? trim($_GET['action']) : '';
$gym_style_type = isset($_GET['type']) && @in_array($_GET['type'], $type_expected) ? $_GET['type'] : '';
$theme_id = isset($_GET['theme_id']) ? @intval($_GET['theme_id']) : '';

if (empty($language) && empty($action) && empty($gym_style_type) && empty($theme_id)) {
	// grabb vars like this because browser are not aggreeing on how to handle & in xml. FF only accpet & where IE and opera only accept &amp;
	$qs = isset($_SERVER['QUERY_STRING']) ? trim($_SERVER['QUERY_STRING']) : '';
	if ($qs && preg_match('`action-(rss|google),type-(xsl),lang-([a-z_]+),theme_id-([0-9]+)`i', $qs, $matches )) {
		$language = $matches[3];
		$action = in_array($matches[1], $action_expected) ? $matches[1] : '';
		$gym_style_type = in_array($matches[2], $type_expected) ? $matches[2] : '';
		$theme_id = intval($matches[4]);
	}
}
$content_type = $gym_style_type == 'css' ? 'text/css' : 'text/xml';
// Expire time of 15 days if not recached
$cache_ttl = 15*86400;
$recache = false;
$theme = false;
$lang = array();
// Let's go
if (!empty($action) && !empty($gym_style_type) && !empty($language) && !empty($theme_id)) {
	// detect ssl
	$ssl_requested = (bool) ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === true)) || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443));
	$ssl_bit = $ssl_requested ? 'ssl_' : '';
	// build cache file name
	$cached_file = "{$gym_cache_path}style_{$action}_{$ssl_bit}{$language}_$theme_id.$gym_style_type";
	if (file_exists($cached_file)) {
		$cached_time = filemtime($cached_file);
		$expire_time = $cached_time + $cache_ttl;
		$recache = $expire_time < time() ? true : /*(filemtime($style_file) > $cached_time ? true :*/ false/*)*/;
	} else {
		$recache = true;
		$expire_time = time() + $cache_ttl;
	}
	if (!$recache) {
		header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', $expire_time));
		header('Content-type: ' . $content_type . '; charset=UTF-8');
		readfile($cached_file);
		// We are done with this call
		exit;
	} else {
		// Include files
		require($phpbb_root_path . 'config.' . $phpEx);
		if (empty($acm_type) || empty($dbms)) {
			exit;
		}
		require($phpbb_root_path . 'includes/acm/acm_' . $acm_type . '.' . $phpEx);
		require($phpbb_root_path . 'includes/cache.' . $phpEx);
		require($phpbb_root_path . 'includes/db/' . $dbms . '.' . $phpEx);
		require($phpbb_root_path . 'includes/constants.' . $phpEx);
		require($phpbb_root_path . 'gym_sitemaps/includes/gym_common.' . $phpEx);
		$db = new $sql_db();
		$cache = new cache();
		// Connect to DB
		if (!@$db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false)) {
			exit;
		}
		unset($dbhost, $dbuser, $dbpasswd, $dbname, $dbport);
		$config = $cache->obtain_config();
		$gym_config = array();
		obtain_gym_config($action, $gym_config);
		// Do we load phpbb css ?
		$load_phpbb_css = isset($gym_config[$action .  '_load_phpbb_css']) ? $gym_config[$action .  '_load_phpbb_css'] : $load_phpbb_css;

		// Check if requested style does exists
		if ($theme_id > 0) {
			$sql = 'SELECT s.style_id, c.theme_path, c.theme_name, t.template_path
				FROM ' . STYLES_TABLE . ' s, ' . STYLES_TEMPLATE_TABLE . ' t, ' . STYLES_THEME_TABLE . ' c
				WHERE s.style_id = ' . $theme_id . '
					AND t.template_id = s.template_id
					AND c.theme_id = s.theme_id';
			$result = $db->sql_query($sql, 300);
			$theme = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
		}
		// Grabb the default one data instead
		if (!$theme) {
			// grabb the first available one
			$theme_id = (int) $config['default_style'];
			$sql = 'SELECT s.style_id, c.theme_path, c.theme_name, t.template_path
				FROM ' . STYLES_TABLE . ' s, ' . STYLES_TEMPLATE_TABLE . ' t, ' . STYLES_THEME_TABLE . ' c
				WHERE s.style_id = ' . $theme_id . '
					AND t.template_id = s.template_id
					AND c.theme_id = s.theme_id';
			$result = $db->sql_query($sql, 300);
			$theme = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
		}
		$db->sql_close();
		if (!empty($cache)) {
			$cache->unload();
		}
		// Determine style file name
		$tpath = $gym_style_type == 'xsl' ? $theme['template_path'] . '/template/gym_sitemaps' : $theme['theme_path'] . '/theme';
		$style_file = $phpbb_root_path . "styles/$tpath/gym_{$action}.$gym_style_type";
		if (!file_exists($style_file)) {
			// Degrade to default styling
			$style_file = $phpbb_root_path . "gym_sitemaps/style/gym_{$action}.$gym_style_type";
			$load_phpbb_css = false;
		}
		// Load the language file
		if (file_exists($phpbb_root_path . 'language/' . $language . '/gym_sitemaps/gym_common.' . $phpEx)) {
			require($phpbb_root_path . 'language/' . $language . '/gym_sitemaps/gym_common.' . $phpEx);
			require($phpbb_root_path . 'language/' . $language . '/common.' . $phpEx);
		} else { // Try with the default language
			$language = $config['default_lang'];
			if (file_exists($phpbb_root_path . 'language/' . $language . '/gym_sitemaps/gym_common.' . $phpEx)) {
				require($phpbb_root_path . 'language/' . $language . '/gym_sitemaps/gym_common.' . $phpEx);
				require($phpbb_root_path . 'language/' . $language . '/common.' . $phpEx);
			} else {
				// try english as a last resort
				$language = 'en';
				if (file_exists($phpbb_root_path . 'language/' . $language . '/gym_sitemaps/gym_common.' . $phpEx)) {
					require($phpbb_root_path . 'language/' . $language . '/gym_sitemaps/gym_common.' . $phpEx);
					require($phpbb_root_path . 'language/' . $language . '/common.' . $phpEx);
				} else {
					$language = 'none';
				}
			}
		}
		// Do not recache if up to date, recompile only if the source stylesheet was updated
		$cached_file = "{$gym_cache_path}style_{$action}_{$ssl_bit}{$language}_$theme_id.$gym_style_type";
		if (file_exists($cached_file)) {
			$cached_time = filemtime($cached_file);
			$expire_time = $cached_time + $cache_ttl;
			$recache = $expire_time < time() ? true : (@filemtime($style_file) > $cached_time ? true : false);
		} else {
			$recache = true;
			$expire_time = time() + $cache_ttl;
		}
		if (!$recache) {
			header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', $expire_time));
			header('Content-type: ' . $content_type . '; charset=UTF-8');
			readfile($cached_file);
			exit;
		}
		// No available style
		if (!$theme) {
			exit;
		}


		// Path Settings
		$ssl_forced = (bool) (($config['server_protocol'] === 'https//'));
		$ssl_use = (bool) ($ssl_requested || $ssl_forced);
		$server_protocol = $ssl_use ? 'https://' : 'http://';
		$server_name = trim($config['server_name'], '/ ');
		$server_port = max(0, (int) $config['server_port']);
		$server_port = ($server_port && $server_port <> 80) ? ':' . $server_port . '/' : '/';
		$script_path = trim($config['script_path'], '/ ');
		$script_path = (empty($script_path) ) ? '' : $script_path . '/';
		$root_url = strtolower($server_protocol . $server_name . $server_port);
		// First grabb the online style
		$phpbb_url = $root_url . $script_path;
		// Parse Theme Data
		$replace = array(
			'{T_IMAGE_PATH}'	=> "{$phpbb_url}gym_sitemaps/images/",
			'{T_STYLE_PATH}'	=> "{$phpbb_url}gym_sitemaps/style/",
			'{S_CONTENT_DIRECTION}'	=> gym_style_lang('DIRECTION', 'ltr'),
			'{S_USER_LANG}'		=> $language,
			'{NO_LANGUAGE_FILES}'	=> empty($lang) ? '<div style="padding:10px;color:red;font-weight:bold;font-size:2em;text-align:center">Required language files for GYM sitemaps are missing in this language pack !!</div>' : '',
		);
		if ($gym_style_type == 'xsl') {
			$replace = array_merge($replace, array(
				'{T_CSS_PATH}'		=> "{$phpbb_url}gym_sitemaps/gym_style.$phpEx?action=$action&amp;type=css&amp;lang={$language}&amp;theme_id={$theme_id}",
				'{L_HOME}'		=> gym_style_lang('GYM_HOME'),
				'{L_FORUM_INDEX}'	=> gym_style_lang('GYM_FORUM_INDEX'),
				'{L_LINK}'		=> gym_style_lang('GYM_LINK'),
				'{L_LASTMOD_DATE}'	=> gym_style_lang('GYM_LASTMOD_DATE'),
				'{ROOT_URL}'		=> $root_url,
				'{HTTP_PROTO_REQUEST}'	=> $server_protocol,
				'{PHPBB_URL}'		=> $phpbb_url,
				// Do not remove !
				'{L_COPY}'		=>  '<a href="http://www.phpbb-seo.com/" title="GYM Sitemaps &amp; RSS &#169; 2006, ' . date('Y') . ' phpBB SEO" class="copyright"><img src="' . $phpbb_url . 'gym_sitemaps/images/phpbb-seo.png" width="80" height="15" alt="' . gym_style_lang('GYM_SEO', 'GYM Sitemaps') . '"/></a>',
				'{L_SEARCH_ADV_EXPLAIN}' => gym_style_lang('SEARCH_ADV_EXPLAIN'),
				'{L_CHANGE_FONT_SIZE}'  => gym_style_lang('CHANGE_FONT_SIZE'),
				'{L_SEARCH_ADV}' 	=> gym_style_lang('SEARCH_ADV'),
				'{L_SEARCH}' 		=> gym_style_lang('SEARCH'),
				'{L_BACK_TO_TOP}' 	=> gym_style_lang('BACK_TO_TOP'),
				'{L_FAQ}' 		=> gym_style_lang('FAQ'),
				'{L_FAQ_EXPLAIN}' 	=> gym_style_lang('FAQ_EXPLAIN'),
				'{L_REGISTER}' 		=> gym_style_lang('REGISTER'),
				'{L_SKIP}' 		=> gym_style_lang('SKIP'),
				'{L_BOOKMARK_THIS}' 	=> gym_style_lang('GYM_BOOKMARK_THIS'),
				'{SITENAME}' 		=> $config['sitename'],
				'{SITE_DESCRIPTION}' 	=> $config['site_desc'],

			));
			if ($action == 'google') {
				$replace = array_merge($replace, array(
					'{L_SITEMAP}'		=> gym_style_lang('GOOGLE_SITEMAP'),
					'{L_SITEMAP_OF}'	=> gym_style_lang('GOOGLE_SITEMAP_OF'),
					'{L_SITEMAPINDEX}'	=> gym_style_lang('GOOGLE_SITEMAPINDEX'),
					'{L_NUMBER_OF_SITEMAP}'	=> gym_style_lang('GOOGLE_NUMBER_OF_SITEMAP'),
					'{L_SITEMAP_URL}'	=> gym_style_lang('GOOGLE_SITEMAP_URL'),
					'{L_NUMBER_OF_URL}'	=> gym_style_lang('GOOGLE_NUMBER_OF_URL'),
					'{L_CHANGEFREQ}'	=> gym_style_lang('GOOGLE_CHANGEFREQ'),
					'{L_PRIORITY}'		=> gym_style_lang('GOOGLE_PRIORITY'),
				));
			} elseif ($action == 'rss') {
				$replace = array_merge($replace, array(
					'{L_UPDATE}'		=> gym_style_lang('RSS_UPDATE'),
					'{L_LAST_UPDATE}'	=> gym_style_lang('RSS_LAST_UPDATE'),
					'{L_MINUTES}'		=> gym_style_lang('GYM_MINUTES'),
					'{L_SOURCE}'		=> gym_style_lang('GYM_SOURCE'),
					'{L_SUBSCRIBE_POD}'	=> gym_style_lang('RSS_SUBSCRIBE_POD'),
					'{L_SUBSCRIBE}'		=> gym_style_lang('RSS_SUBSCRIBE'),
					'{L_2_LINK}'		=> gym_style_lang('RSS_2_LINK'),
					'{L_FEED}'		=> gym_style_lang('RSS_FEED'),
					'{L_ITEM_LISTED}'	=> gym_style_lang('RSS_ITEM_LISTED'),
					'{L_ITEMS_LISTED}'	=> gym_style_lang('RSS_ITEMS_LISTED'),
					'{L_RSS_VALID}'		=> gym_style_lang('RSS_VALID'),
				));
			}
		}
		// Load the required stylsheet template
		if ( $load_phpbb_css && $gym_style_type == 'css' ) {
			@ini_set('user_agent','GYM Sitemaps &amp; RSS / www.phpBB-SEO.com');
			@ini_set('default_socket_timeout', 10);
			$phpbb_css = @file_get_contents("{$phpbb_url}style.php?id={$theme_id}&lang={$language}");
			if ($phpbb_css) {
				$output = str_replace('./styles/', "{$phpbb_url}styles/", $phpbb_css);
			} else {
				$style_tpl = @file_get_contents($style_file);
				$output = str_replace(array_keys($replace), array_values($replace), $style_tpl);
			}
			unset($phpbb_css);
		} else {
			$style_tpl = @file_get_contents($style_file);
			$output = str_replace(array_keys($replace), array_map('numeric_entify_utf8', array_values($replace)), $style_tpl);
		}
		if ($strip_spaces) {
			if ($gym_style_type === 'xsl') {
				$output = preg_replace(array('`<\!--.*-->`Us', '`[\s]+`'), ' ', $output);
			} else {
				$output = preg_replace(array('`/\*.*\*/`Us', '`[\s]+`'), ' ', $output);
			}
		}
		$handle = @fopen($cached_file, 'wb');
		@flock($handle, LOCK_EX);
		@fwrite($handle, $output);
		@flock($handle, LOCK_UN);
		@fclose ($handle);
		@chmod($cached_file, 0666);

		header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', $expire_time));
		header('Content-type: ' . $content_type . '; charset=UTF-8');
		echo $output;
		exit;
	}
}
exit;
/**
* A little helper for those who forgot the language files
*/
function gym_style_lang($key, $default = '') {
	global $lang;
	return isset($lang[$key]) ? $lang[$key] : ($default ? $default : '&#123; ' . $key . ' &#125;');
}
?>