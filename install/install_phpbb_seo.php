<?php
/**
*
* @package Ultimate SEO URL phpBB SEO
* @version $Id: install_phpbb_seo.php 158 2009-11-18 08:50:19Z dcz $
* @copyright (c) 2006 - 2009 www.phpbb-seo.com
* @license http://www.opensource.org/licenses/rpl1.5.txt Reciprocal Public License 1.5
*
*/

/**
*/

if ( !defined('IN_INSTALL') )
{
	// Someone has tried to access the file direct. This is not a good idea, so exit
	exit;
}

if (!empty($setmodules))
{
	$module[] = array(
		'module_type'		=> 'install',
		'module_title'		=> 'SEO_PREMOD',
		'module_filename'	=> substr(basename(__FILE__), 0, -strlen($phpEx)-1),
		'module_order'		=> -1,
		'module_subs'		=> array('INTRO', 'LICENSE', 'SUPPORT'),
		'module_stages'		=> '',
		'module_reqs'		=> ''
	);
}

/**
* Main Tab - Installation
* @package install
*/
class install_phpbb_seo extends module {
	function install_phpbb_seo(&$p_master) {
		$this->p_master = &$p_master;
	}
	function main($mode, $sub) {
		global $lang, $template, $language;

		switch ($sub) {
			case 'intro' :
				$title = $lang['SEO_PREMOD_TITLE'];
				$body = $lang['SEO_PREMOD_BODY'];
			break;
			case 'license' :
				$title = $lang['SEO_LICENCE_TITLE'];
				$body = '<p>' . $lang['SEO_PREMOD_LICENCE'] . '</p><br/><hr/>' . implode("<br/>\n", file('./docs/COPYING'));
			break;
			case 'support' :
				$title = $lang['SEO_SUPPORT_TITLE'];
				$body = $lang['SEO_PREMOD_SUPPORT_BODY'];
			break;
		}
		$this->tpl_name = 'install_main';
		$this->page_title = $title;
		$template->assign_vars(array(
			'TITLE'		=> $title,
			'BODY'		=> $body,

			'S_LANG_SELECT'	=> '<select id="language" name="language">' . $this->p_master->inst_language_select($language) . '</select>',
		));
	}
}

?>
