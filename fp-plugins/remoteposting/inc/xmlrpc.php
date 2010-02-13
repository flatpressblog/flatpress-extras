<?php
/**
 * XML-RPC protocol support for WordPress
 *
 * @license GPL v2 <./license.txt>
 * @package WordPress
 */

/**
 * Whether this is a XMLRPC Request
 *
 * @var bool
 */
define('XMLRPC_REQUEST', true); function __($s) { return $s; };

// Some browser-embedded clients send cookies. We don't want them.
$_COOKIE = array();

// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
// but we can do it ourself.
if ( !isset( $HTTP_RAW_POST_DATA ) ) {
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
}

// fix for mozBlog and other cases where '<?xml' isn't on the very first line
if ( isset($HTTP_RAW_POST_DATA) )
	$HTTP_RAW_POST_DATA = trim($HTTP_RAW_POST_DATA);

/** Include the bootstrap for setting up FlatPress environment */
#include './defaults.php';
#include INCLUDES_DIR . 'includes.php';

#system_init();

#include './class-IXR.php';

if ( isset( $_GET['rsd'] ) ) { // http://archipelago.phrasewise.com/rsd
header('Content-Type: text/xml; charset=' . $fp_config['locale']['charset']);
?>
<?php echo '<?xml version="1.0" encoding="'.$fp_config['locale']['charset'].'"?'.'>'; ?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
  <service>
    <engineName>FlatPress</engineName>
    <engineLink>http://www.flatpress.org/</engineLink>
    <homePageLink><?php echo BLOG_BASEURL;?></homePageLink>
    <apis>  <api name="MetaWeblog" blogID="1" preferred="true" apiLink="<?php echo BLOG_BASEURL ?>?xmlrpc" /> </apis>
    
<?php /*
	<api name="WordPress" blogID="1" preferred="true" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Movable Type" blogID="1" preferred="false" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Blogger" blogID="1" preferred="false" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Atom" blogID="" preferred="false" apiLink="<?php echo apply_filters('atom_service_url', site_url('wp-app.php/service') ) ?>" />
    */
?>
  </service>
</rsd>
<?php
exit;
}


// Turn off all warnings and errors.
// error_reporting(0);

/**
 * Posts submitted via the xmlrpc interface get that title
 * @name post_default_title
 * @var string
 */
$post_default_title = "";

/**
 * Whether to enable XMLRPC Logging.
 *
 * @name xmlrpc_logging
 * @var int|bool
 */
$xmlrpc_logging = 1;

/**
 * logIO() - Writes logging info to a file.
 *
 * @uses $xmlrpc_logging
 * @package WordPress
 * @subpackage Logging
 *
 * @param string $io Whether input or output
 * @param string $msg Information describing logging reason.
 * @return bool Always return true
 */
function logIO($io,$msg) {
	$xmlrpc_logging = 0;
	if ($xmlrpc_logging) {
		$fp = fopen("xmlrpc.log","a+");
		$date = gmdate("Y-m-d H:i:s ");
		$iot = ($io == "I") ? " Input: " : " Output: ";
		fwrite($fp, "\n\n".$date.$iot.$msg);
		fclose($fp);
	}
	return true;
}

if ( isset($HTTP_RAW_POST_DATA) )
	logIO("I", $HTTP_RAW_POST_DATA);

/**
 * WordPress XMLRPC server implementation.
 *
 * Implements compatability for Blogger API, MetaWeblog API, MovableType, and
 * pingback. Additional WordPress API for managing comments, pages, posts,
 * options, etc.
 *
 * Since WordPress 2.6.0, WordPress XMLRPC server can be disabled in the
 * administration panels.
 *
 * @package WordPress
 * @subpackage Publishing
 * @since 1.5.0
 */
class wp_xmlrpc_server extends IXR_Server {

	/**
	 * Register all of the XMLRPC methods that XMLRPC server understands.
	 *
	 * PHP4 constructor and sets up server and method property. Passes XMLRPC
	 * methods through the 'xmlrpc_methods' filter to allow plugins to extend
	 * or replace XMLRPC methods.
	 *
	 * @since 1.5.0
	 *
	 * @return wp_xmlrpc_server
	 */
	function wp_xmlrpc_server() {
		$this->methods = array(
			// WordPress API
			/*
			'wp.getUsersBlogs'		=> 'this:wp_getUsersBlogs',
			'wp.getPage'			=> 'this:wp_getPage',
			'wp.getPages'			=> 'this:wp_getPages',
			'wp.newPage'			=> 'this:wp_newPage',
			'wp.deletePage'			=> 'this:wp_deletePage',
			'wp.editPage'			=> 'this:wp_editPage',
			'wp.getPageList'		=> 'this:wp_getPageList',
			'wp.getAuthors'			=> 'this:wp_getAuthors',
			'wp.getCategories'		=> 'this:mw_getCategories',		// Alias
			'wp.getTags'			=> 'this:wp_getTags',
			'wp.newCategory'		=> 'this:wp_newCategory',
			'wp.deleteCategory'		=> 'this:wp_deleteCategory',
			'wp.suggestCategories'	=> 'this:wp_suggestCategories',
			'wp.getCommentCount'	=> 'this:wp_getCommentCount',
			'wp.getPostStatusList'	=> 'this:wp_getPostStatusList',
			'wp.getPageStatusList'	=> 'this:wp_getPageStatusList',
			'wp.getPageTemplates'	=> 'this:wp_getPageTemplates',
			'wp.getOptions'			=> 'this:wp_getOptions',
			'wp.setOptions'			=> 'this:wp_setOptions',
			'wp.getComment'			=> 'this:wp_getComment',
			'wp.getComments'		=> 'this:wp_getComments',
			'wp.deleteComment'		=> 'this:wp_deleteComment',
			'wp.editComment'		=> 'this:wp_editComment',
			'wp.newComment'			=> 'this:wp_newComment',
			'wp.getCommentStatusList' => 'this:wp_getCommentStatusList',*/

			'wp.uploadFile'			=> 'this:mw_newMediaObject',	// Alias
			'wp.getCategories'		=> 'this:mw_getCategories',		// Alias
			// Blogger API
			'blogger.getUsersBlogs' => 'this:blogger_getUsersBlogs',
			'blogger.getUserInfo' => 'this:blogger_getUserInfo',
			'blogger.getPost' => 'this:blogger_getPost',
			'blogger.getRecentPosts' => 'this:blogger_getRecentPosts',
			'blogger.getTemplate' => 'this:blogger_getTemplate',
			'blogger.setTemplate' => 'this:blogger_setTemplate',
			'blogger.newPost' => 'this:blogger_newPost',
			'blogger.editPost' => 'this:blogger_editPost',
			'blogger.deletePost' => 'this:blogger_deletePost',

			// MetaWeblog API (with MT extensions to structs)
			'metaWeblog.newPost' => 'this:mw_newPost',
			'metaWeblog.editPost' => 'this:mw_editPost',
			'metaWeblog.getPost' => 'this:mw_getPost',
			'metaWeblog.getRecentPosts' => 'this:mw_getRecentPosts',
			'metaWeblog.getCategories' => 'this:mw_getCategories',
			'metaWeblog.newMediaObject' => 'this:mw_newMediaObject',

			// MetaWeblog API aliases for Blogger API
			// see http://www.xmlrpc.com/stories/storyReader$2460
			'metaWeblog.deletePost' => 'this:blogger_deletePost',
			'metaWeblog.getTemplate' => 'this:blogger_getTemplate',
			'metaWeblog.setTemplate' => 'this:blogger_setTemplate',
			'metaWeblog.getUsersBlogs' => 'this:blogger_getUsersBlogs',

			'mt.getPostCategories' => 'this:mt_getPostCategories',
			'mt.setPostCategories' => 'this:mt_setPostCategories',
			/*/ MovableType API
			'mt.supportedMethods' => 'this:mt_supportedMethods',
			'mt.getCategoryList' => 'this:mt_getCategoryList',
			'mt.getCategoryList' => 'this:mt_getCategoryList',
			'mt.getRecentPostTitles' => 'this:mt_getRecentPostTitles',
			'mt.supportedTextFilters' => 'this:mt_supportedTextFilters',
			'mt.getTrackbackPings' => 'this:mt_getTrackbackPings',
			'mt.publishPost' => 'this:mt_publishPost',

			// PingBack
			'pingback.ping' => 'this:pingback_ping',
			'pingback.extensions.getPingbacks' => 'this:pingback_extensions_getPingbacks',*/

			'demo.sayHello' => 'this:sayHello',
			'demo.addTwoNumbers' => 'this:addTwoNumbers'
		);

		$this->initialise_blog_option_info( );
		$this->methods = apply_filters('xmlrpc_methods', $this->methods);
		$this->IXR_Server($this->methods);
	}

	/**
	 * Test XMLRPC API by saying, "Hello!" to client.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method Parameters.
	 * @return string
	 */
	function sayHello($args) {
		return 'Hello!';
	}

	/**
	 * Test XMLRPC API by adding two numbers for client.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method Parameters.
	 * @return int
	 */
	function addTwoNumbers($args) {
		$number1 = $args[0];
		$number2 = $args[1];
		return $number1 + $number2;
	}

	/**
	 * Check user's credentials.
	 *
	 * @since 1.5.0
	 *
	 * @param string $user_login User's username.
	 * @param string $user_pass User's password.
	 * @return bool Whether authentication passed.
	 */
	function login_pass_ok($user_login, $user_pass) {
		/*
		if ( !get_option( 'enable_xmlrpc' ) ) {
			$this->error = new IXR_Error( 405, sprintf( __( 'XML-RPC services are disabled on this blog.  An admin user can enable them at %s'),  admin_url('options-writing.php') ) );
			return false;
		}
		*/

		if (!user_login($user_login, $user_pass)) {
			$this->error = new IXR_Error(403, __('Bad login/pass combination.'));
			return false;
		}
		return true;
	}

	/**
	 * Sanitize string or array of strings for database.
	 *
	 * @since 1.5.2
	 *
	 * @param string|array $array Sanitize single string or array of strings.
	 * @return string|array Type matches $array and sanitized for the database.
	 */
	function escape(&$array) {
		global $wpdb;

		if(!is_array($array)) {
			return($wpdb->escape($array));
		}
		else {
			foreach ( (array) $array as $k => $v ) {
				if (is_array($v)) {
					$this->escape($array[$k]);
				} else if (is_object($v)) {
					//skip
				} else {
					$array[$k] = $wpdb->escape($v);
				}
			}
		}
	}

	/**
	 * Retrieve custom fields for post.
	 *
	 * @since 2.5.0
	 *
	 * @param int $post_id Post ID.
	 * @return array Custom fields, if exist.
	 */
	function get_custom_fields($post_id) {
		$post_id = (int) $post_id;

		$custom_fields = array();

		foreach ( (array) has_meta($post_id) as $meta ) {
			// Don't expose protected fields.
			if ( strpos($meta['meta_key'], '_wp_') === 0 ) {
				continue;
			}

			$custom_fields[] = array(
				"id"    => $meta['meta_id'],
				"key"   => $meta['meta_key'],
				"value" => $meta['meta_value']
			);
		}

		return $custom_fields;
	}

	/**
	 * Set custom fields for post.
	 *
	 * @since 2.5.0
	 *
	 * @param int $post_id Post ID.
	 * @param array $fields Custom fields.
	 */
	function set_custom_fields($post_id, $fields) {
		$post_id = (int) $post_id;

		foreach ( (array) $fields as $meta ) {
			if ( isset($meta['id']) ) {
				$meta['id'] = (int) $meta['id'];

				if ( isset($meta['key']) ) {
					update_meta($meta['id'], $meta['key'], $meta['value']);
				}
				else {
					delete_meta($meta['id']);
				}
			}
			else {
				$_POST['metakeyinput'] = $meta['key'];
				$_POST['metavalue'] = $meta['value'];
				add_meta($post_id);
			}
		}
	}

	/**
	 * Setup blog options property.
	 *
	 * Passes property through 'xmlrpc_blog_options' filter.
	 *
	 * @since 2.6.0
	 */
	function initialise_blog_option_info( ) {
		global $wp_version;

		$this->blog_options = array(
			// Read only options
			'software_name'		=> array(
				'desc'			=> __( 'Software Name' ),
				'readonly'		=> true,
				'value'			=> 'WordPress'
			),
			'software_version'	=> array(
				'desc'			=> __( 'Software Version' ),
				'readonly'		=> true,
				'value'			=> $wp_version
			),
			'blog_url'			=> array(
				'desc'			=> __( 'Blog URL' ),
				'readonly'		=> true,
				'option'		=> 'siteurl'
			),

			// Updatable options
			'time_zone'			=> array(
				'desc'			=> __( 'Time Zone' ),
				'readonly'		=> false,
				'option'		=> 'gmt_offset'
			),
			'blog_title'		=> array(
				'desc'			=> __( 'Blog Title' ),
				'readonly'		=> false,
				'option'			=> 'blogname'
			),
			'blog_tagline'		=> array(
				'desc'			=> __( 'Blog Tagline' ),
				'readonly'		=> false,
				'option'		=> 'blogdescription'
			),
			'date_format'		=> array(
				'desc'			=> __( 'Date Format' ),
				'readonly'		=> false,
				'option'		=> 'date_format'
			),
			'time_format'		=> array(
				'desc'			=> __( 'Time Format' ),
				'readonly'		=> false,
				'option'		=> 'time_format'
			)
		);

		$this->blog_options = apply_filters( 'xmlrpc_blog_options', $this->blog_options );
	}
	/**
	 * Retrieve blogs that user owns.
	 *
	 * Will make more sense once we support multiple blogs.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function blogger_getUsersBlogs($args) {

		global $fp_config;

		$user_login = $args[1];
		$user_pass  = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'blogger.getUsersBlogs');


		$struct = array(
			'isAdmin'  => true,
			'url'      => BLOG_BASEURL,
			'blogid'   => '1',
			'blogName' => $fp_config['general']['title'],
			'xmlrpc'   => BLOG_BASEURL . 'xmlrpc.php',
		);

		return array($struct);
	}

	/* MetaWeblog API functions
	 * specs on wherever Dave Winer wants them to be
	 */

	/**
	 * Create a new post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return int
	 */
	function mw_newPost($args) {
		//$this->escape($args);


		$blog_ID     = (int) $args[0]; // we will support this in the near future
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$content	 = $args[3];
		$publish     = $args[4];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}
		$user = user_get($user_login);


		$postdata = array(
			'subject' => $content['title'],
			'content' => $content['description'],
			// date - auto
			'author' => $user['userid'],
			'version' => system_ver()
		);

		if (@$content['categories']) {
			$cat_defs = entry_categories_get('defs');
			foreach ($content['categories'] as $catname) {
				$id = array_search($catname, $cat_defs);
				if ($id !== false) $postdata['categories'][] = $id;
			}
		}

		//$postdata['categories']

		//$post_ID = wp_insert_post($postdata, true);
		$e = entry_save($postdata);
		if (!is_string($e))
			return new IXR_Error(500, 'internal error');


		return $e;
	}

	/**
	 * Attach upload to a post.
	 *
	 * @since 2.1.0
	 *
	 * @param int $post_ID Post ID.
	 * @param string $post_content Post Content for attachment.
	 */
	function attach_uploads( $post_ID, $post_content ) {
		global $wpdb;

		// find any unattached files
		$attachments = $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts} WHERE post_parent = '-1' AND post_type = 'attachment'" );
		if( is_array( $attachments ) ) {
			foreach( $attachments as $file ) {
				if( strpos( $post_content, $file->guid ) !== false ) {
					$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->posts} SET post_parent = %d WHERE ID = %d", $post_ID, $file->ID) );
				}
			}
		}
	}

	/**
	 * Edit a post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return bool True on success.
	 */
	function mw_editPost($args) {

		$post_ID     = $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$content	 = $args[3];
		$publish     = $args[4];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}
		//$user = user_get($user_login);

		do_action('xmlrpc_call', 'metaWeblog.editPost');

		$postdata = entry_parse($post_ID);
		if (!$postdata) return false;

		$postdata['subject'] = $content['title'];
		$postdata['content'] = $content['description'];
		io_write_file('LOG', var_export($postdata,1));
		
		// categories
		

		$postdata['categories'] = array();
		if (@$content['categories']) {
			$cat_defs = entry_categories_get('defs');
			foreach ($content['categories'] as $catname) {
				$id = array_search($catname, $cat_defs);
				if ($id !== false) $postdata['categories'][] = $id;
			}
		}

		$e = entry_save($postdata);

		return is_string($e)? true : false;
	}

	/**
	 * Retrieve post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mw_getPost($args) {
		
		$post_ID     = $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'metaWeblog.getPost');

		$postdata = entry_parse($post_ID);
		$categories = array();
		$cat_defs = entry_categories_get('defs');
		if (isset($postdata['categories'])) {
			foreach ($postdata['categories'] as $id) {
				if (is_numeric($id))
					$categories[] = $cat_defs[$id];
			}
		}


		if ($postdata) {

			$link = get_permalink($post_ID);
			$resp = array(
				'dateCreated' => new IXR_Date($postdata['date']),
				'userid' => $postdata['author'],
				'postid' => $post_ID,
				'description' => $postdata['content'],
				'title' => $postdata['subject'],
				'link' => $link,
				'permaLink' => $link,
				// commented out because no other tool seems to use this
				//	      'content' => $entry['post_content'],
				'categories' => $categories,
				//'mt_excerpt' => $postdata['post_excerpt'],
				//'mt_text_more' => $post['extended'],
				//'mt_allow_comments' => $allow_comments,
				//'mt_allow_pings' => $allow_pings,
				//'mt_keywords' => $tagnames,
				//'wp_slug' => $postdata['post_name'],
				//'wp_password' => $postdata['post_password'],
				//'wp_author_id' => $author->ID,
				//'wp_author_display_name'	=> $author->display_name,
				'date_created_gmt' => new IXR_Date($postdata['date']),
				//'post_status' => $postdata['post_status'],
				//'custom_fields' => $this->get_custom_fields($post_ID)
			);

			//if (!empty($enclosure)) $resp['enclosure'] = $enclosure;

			return $resp;
		} else {
			return new IXR_Error(404, __('Sorry, no such post.'));
		}
	}

	function blogger_deletePost($args) {

		$id = $args[1];
		$user_login = $args[2];
		$user_pass = $args[3];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		return (bool)entry_delete($id);


	}

	/**
	 * Retrieve list of recent posts.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mw_getRecentPosts($args) {

		$blog_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$count 		 = (int) $args[3];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		$user = user_get($user_login);

		do_action('xmlrpc_call', 'metaWeblog.getRecentPosts');

		$resp_arr = array();

		$q = new FPDB_Query(array('start'=>0, 'count'=>$count, 'fullparse'=>true), null);


		while ($q->hasMore()) {

			list($post_ID, $postdata) = $q->getEntry();

			$link = get_permalink($post_ID);
			$resp = array(
				'dateCreated' => new IXR_Date($postdata['date']),
				'userid' => $user['userid'], //$postdata['author'],
				'postid' => $post_ID,
				// 'description' ...
				'title' => $postdata['subject'],
				'link' => $link,
				'permaLink' => $link,
				// commented out because no other tool seems to use this
				//	      'content' => $entry['post_content'],
				// 'categories' => $categories,
				//'mt_excerpt' => $postdata['post_excerpt'],
				//'mt_text_more' => $post['extended'],
				//'mt_allow_comments' => $allow_comments,
				//'mt_allow_pings' => $allow_pings,
				//'mt_keywords' => $tagnames,
				//'wp_slug' => $postdata['post_name'],
				//'wp_password' => $postdata['post_password'],
				//'wp_author_id' => $author->ID,
				//'wp_author_display_name'	=> $author->display_name,
				'date_created_gmt' => new IXR_Date($postdata['date']),
				//'post_status' => $postdata['post_status'],
				//'custom_fields' => $this->get_custom_fields($post_ID)
			);

			$resp_arr[] = $resp;

			//if (!empty($enclosure)) $resp['enclosure'] = $enclosure;

		}
		if ($resp_arr) {
			return $resp_arr;
		} else {
			return new IXR_Error(404, __('Sorry, no such post.'));
		}
	


	}

	/**
	 * Retrieve the list of categories on a given blog.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mw_getCategories($args) {

		// $this->escape($args);

		$blog_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'metaWeblog.getCategories');

		$categories_struct = array();

		if ( $cats = entry_categories_list() ) {
			$defs = entry_categories_get('defs');
			foreach ( $cats as $id => $parent ) {
				$struct['categoryId'] = $id;
				$struct['parentId'] = $parent;
				// $struct['description'] = $cat->description;
				$struct['categoryName'] = $defs[$id];
				// $struct['htmlUrl'] = wp_specialchars(get_category_link($id));
				// $struct['rssUrl'] = wp_specialchars(get_category_feed_link($id, 'rss2'));

				$categories_struct[] = $struct;
			}
		}

		return $categories_struct;
	}

	/**
	 * Uploads a file, following your settings.
	 *
	 * Adapted from a patch by Johann Richard.
	 *
	 * @link http://mycvs.org/archives/2004/06/30/file-upload-to-wordpress-in-ecto/
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mw_newMediaObject($args) {
		global $wpdb;

		$blog_ID    = (int) $args[0];
		$username  	= $args[1];
		$password   = $args[2];
		$data       = $args[3];

		if ( !$user = $this->login_pass_ok($username, $password) ) {
			return $this->error;
		}

		$dir = ATTACHS_DIR;
					
		$name = $data['name'];
		$ext = strtolower(strrchr($name,'.'));
					
		$imgs = array('.jpg','.gif','.png', '.jpeg'); 
		if (in_array($ext,$imgs)) {
			$dir = IMAGES_DIR;
		}
		
		if (!file_exists(IMAGES_DIR))
			fs_mkdir(IMAGES_DIR);
			
		if (!file_exists(ATTACHS_DIR))
			fs_mkdir(ATTACHS_DIR);
			
							
		$name = sanitize_title(substr($name, 0, -strlen($ext))) . $ext;
	


		$type = $data['type'];
		$bits = $data['bits'];

		logIO('O', '(MW) Received '.strlen($bits).' bytes');

		do_action('xmlrpc_call', 'metaWeblog.newMediaObject');
		
		/*
		if ( !current_user_can('upload_files') ) {
			logIO('O', '(MW) User does not have upload_files capability');
			$this->error = new IXR_Error(401, __('You are not allowed to upload files to this site.'));
			return $this->error;
		}
		*/

		if ( $upload_err = apply_filters( "pre_upload_error", false ) )
			return new IXR_Error(500, $upload_err);
		
		/* let's pretend everything will be fine
		if(!empty($data["overwrite"]) && ($data["overwrite"] == true)) {
			// Get postmeta info on the object.
			$old_file = $wpdb->get_row("
				SELECT ID
				FROM {$wpdb->posts}
				WHERE post_title = '{$name}'
					AND post_type = 'attachment'
			");

			// Delete previous file.
			wp_delete_attachment($old_file->ID);

			// Make sure the new name is different by pre-pending the
			// previous post id.
			$filename = preg_replace("/^wpid\d+-/", "", $name);
			$name = "wpid{$old_file->ID}-{$filename}";
		}
		*/
		$fpath = $dir.$name;
		$url   = BLOG_BASEURL . $fpath;
		$upload = io_write_file($fpath, $bits);
		if ( $upload === false ) {
			$errorString = sprintf(__('Could not write file %1$s (%2$s)'), $name, $upload['error']);
			logIO('O', '(MW) ' . $errorString);
			return new IXR_Error(500, $errorString);
		}
		/*
		// Construct the attachment array
		// attach to post_id 0
		$post_id = 0;
		$attachment = array(
			'post_title' => $name,
			'post_content' => '',
			'post_type' => 'attachment',
			'post_parent' => $post_id,
			'post_mime_type' => $type,
			'guid' => $upload[ 'url' ]
		);

		// Save the data
		$id = wp_insert_attachment( $attachment, $upload[ 'file' ], $post_id );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		*/

		return apply_filters( 'wp_handle_upload', array( 'file' => $name, 'url' => $url, 'type' => $type ) );

	}

	/* MovableType API functions
	 * specs on http://www.movabletype.org/docs/mtmanual_programmatic.html
	 */

	/**
	 * Retrieve the post titles of recent posts.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mt_getRecentPostTitles($args) {

	}

	/**
	 * Retrieve list of all categories on blog.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mt_getCategoryList($args) {

		$this->escape($args);

		$blog_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		set_current_user( 0, $user_login );
		if( !current_user_can( 'edit_posts' ) )
			return new IXR_Error( 401, __( 'Sorry, you must be able to edit posts on this blog in order to view categories.' ) );

		do_action('xmlrpc_call', 'mt.getCategoryList');

		$categories_struct = array();

		if ( $cats = get_categories('hide_empty=0&hierarchical=0') ) {
			foreach ($cats as $cat) {
				$struct['categoryId'] = $cat->term_id;
				$struct['categoryName'] = $cat->name;

				$categories_struct[] = $struct;
			}
		}

		return $categories_struct;
	}

	/**
	 * Retrieve post categories.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mt_getPostCategories($args) {


		$post_ID     = $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		$user = user_get($user_login);
		do_action('xmlrpc_call', 'mt.getPostCategories');

		$categories = array();
		$entry = entry_parse($post_ID);
		// first listed category will be the primary category

		$defs = entry_categories_get('defs');
		if (!@($entry['categories'])) return array();
		foreach($entry['categories'] as $catid) {
			$categories[] = array(
				'categoryName' => $defs[$catid],
				'categoryId' => (string) $catid,
			);
		}

		return $categories;
	}

	/**
	 * Sets categories for a post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return bool True on success.
	 */
	function mt_setPostCategories($args) {

		$post_ID     = $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$categories  = @$args[3];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'mt.setPostCategories');

		if (!$categories) return true;

		foreach($categories as $cat) {
			$catids[] = $cat['categoryId'];
		}

		$e = entry_parse($post_ID);
		if (!$e) return false;

		$e['categories'] = $catids;

		return true;
	}

	/**
	 * Retrieve an array of methods supported by this server.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mt_supportedMethods($args) {

		do_action('xmlrpc_call', 'mt.supportedMethods');

		$supported_methods = array();
		foreach($this->methods as $key=>$value) {
			$supported_methods[] = $key;
		}

		return $supported_methods;
	}

	/**
	 * Retrieve an empty array because we don't support per-post text filters.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 */
	function mt_supportedTextFilters($args) {
		do_action('xmlrpc_call', 'mt.supportedTextFilters');
		return apply_filters('xmlrpc_text_filters', array());
	}

	/**
	 * Retrieve trackbacks sent to a given post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return mixed
	 */
	function mt_getTrackbackPings($args) {

		global $wpdb;

		$post_ID = intval($args);

		do_action('xmlrpc_call', 'mt.getTrackbackPings');

		$actual_post = wp_get_single_post($post_ID, ARRAY_A);

		if (!$actual_post) {
			return new IXR_Error(404, __('Sorry, no such post.'));
		}

		$comments = $wpdb->get_results( $wpdb->prepare("SELECT comment_author_url, comment_content, comment_author_IP, comment_type FROM $wpdb->comments WHERE comment_post_ID = %d", $post_ID) );

		if (!$comments) {
			return array();
		}

		$trackback_pings = array();
		foreach($comments as $comment) {
			if ( 'trackback' == $comment->comment_type ) {
				$content = $comment->comment_content;
				$title = substr($content, 8, (strpos($content, '</strong>') - 8));
				$trackback_pings[] = array(
					'pingTitle' => $title,
					'pingURL'   => $comment->comment_author_url,
					'pingIP'    => $comment->comment_author_IP
				);
		}
		}

		return $trackback_pings;
	}

	/**
	 * Sets a post's publish status to 'publish'.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return int
	 */
	function mt_publishPost($args) {

		$this->escape($args);

		$post_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'mt.publishPost');

		set_current_user(0, $user_login);
		if ( !current_user_can('edit_post', $post_ID) )
			return new IXR_Error(401, __('Sorry, you can not edit this post.'));

		$postdata = wp_get_single_post($post_ID,ARRAY_A);

		$postdata['post_status'] = 'publish';

		// retain old cats
		$cats = wp_get_post_categories($post_ID);
		$postdata['post_category'] = $cats;
		$this->escape($postdata);

		$result = wp_update_post($postdata);

		return $result;
	}

	/* PingBack functions
	 * specs on www.hixie.ch/specs/pingback/pingback
	 */

	/**
	 * Retrieves a pingback and registers it.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function pingback_ping($args) {
		global $wpdb;

		do_action('xmlrpc_call', 'pingback.ping');

		$this->escape($args);

		$pagelinkedfrom = $args[0];
		$pagelinkedto   = $args[1];

		$title = '';

		$pagelinkedfrom = str_replace('&amp;', '&', $pagelinkedfrom);
		$pagelinkedto = str_replace('&amp;', '&', $pagelinkedto);
		$pagelinkedto = str_replace('&', '&amp;', $pagelinkedto);

		// Check if the page linked to is in our site
		$pos1 = strpos($pagelinkedto, str_replace(array('http://www.','http://','https://www.','https://'), '', get_option('home')));
		if( !$pos1 )
			return new IXR_Error(0, __('Is there no link to us?'));

		// let's find which post is linked to
		// FIXME: does url_to_postid() cover all these cases already?
		//        if so, then let's use it and drop the old code.
		$urltest = parse_url($pagelinkedto);
		if ($post_ID = url_to_postid($pagelinkedto)) {
			$way = 'url_to_postid()';
		} elseif (preg_match('#p/[0-9]{1,}#', $urltest['path'], $match)) {
			// the path defines the post_ID (archives/p/XXXX)
			$blah = explode('/', $match[0]);
			$post_ID = (int) $blah[1];
			$way = 'from the path';
		} elseif (preg_match('#p=[0-9]{1,}#', $urltest['query'], $match)) {
			// the querystring defines the post_ID (?p=XXXX)
			$blah = explode('=', $match[0]);
			$post_ID = (int) $blah[1];
			$way = 'from the querystring';
		} elseif (isset($urltest['fragment'])) {
			// an #anchor is there, it's either...
			if (intval($urltest['fragment'])) {
				// ...an integer #XXXX (simpliest case)
				$post_ID = (int) $urltest['fragment'];
				$way = 'from the fragment (numeric)';
			} elseif (preg_match('/post-[0-9]+/',$urltest['fragment'])) {
				// ...a post id in the form 'post-###'
				$post_ID = preg_replace('/[^0-9]+/', '', $urltest['fragment']);
				$way = 'from the fragment (post-###)';
			} elseif (is_string($urltest['fragment'])) {
				// ...or a string #title, a little more complicated
				$title = preg_replace('/[^a-z0-9]/i', '.', $urltest['fragment']);
				$sql = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title RLIKE %s", $title);
				if (! ($post_ID = $wpdb->get_var($sql)) ) {
					// returning unknown error '0' is better than die()ing
			  		return new IXR_Error(0, '');
				}
				$way = 'from the fragment (title)';
			}
		} else {
			// TODO: Attempt to extract a post ID from the given URL
	  		return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));
		}
		$post_ID = (int) $post_ID;


		logIO("O","(PB) URL='$pagelinkedto' ID='$post_ID' Found='$way'");

		$post = get_post($post_ID);

		if ( !$post ) // Post_ID not found
	  		return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));

		if ( $post_ID == url_to_postid($pagelinkedfrom) )
			return new IXR_Error(0, __('The source URL and the target URL cannot both point to the same resource.'));

		// Check if pings are on
		if ( !pings_open($post) )
	  		return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));

		// Let's check that the remote site didn't already pingback this entry
		$wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_author_url = %s", $post_ID, $pagelinkedfrom) );

		if ( $wpdb->num_rows ) // We already have a Pingback from this URL
	  		return new IXR_Error(48, __('The pingback has already been registered.'));

		// very stupid, but gives time to the 'from' server to publish !
		sleep(1);

		// Let's check the remote site
		$linea = wp_remote_fopen( $pagelinkedfrom );
		if ( !$linea )
	  		return new IXR_Error(16, __('The source URL does not exist.'));

		$linea = apply_filters('pre_remote_source', $linea, $pagelinkedto);

		// Work around bug in strip_tags():
		$linea = str_replace('<!DOC', '<DOC', $linea);
		$linea = preg_replace( '/[\s\r\n\t]+/', ' ', $linea ); // normalize spaces
		$linea = preg_replace( "/ <(h1|h2|h3|h4|h5|h6|p|th|td|li|dt|dd|pre|caption|input|textarea|button|body)[^>]*>/", "\n\n", $linea );

		preg_match('|<title>([^<]*?)</title>|is', $linea, $matchtitle);
		$title = $matchtitle[1];
		if ( empty( $title ) )
			return new IXR_Error(32, __('We cannot find a title on that page.'));

		$linea = strip_tags( $linea, '<a>' ); // just keep the tag we need

		$p = explode( "\n\n", $linea );

		$preg_target = preg_quote($pagelinkedto);

		foreach ( $p as $para ) {
			if ( strpos($para, $pagelinkedto) !== false ) { // it exists, but is it a link?
				preg_match("|<a[^>]+?".$preg_target."[^>]*>([^>]+?)</a>|", $para, $context);

				// If the URL isn't in a link context, keep looking
				if ( empty($context) )
					continue;

				// We're going to use this fake tag to mark the context in a bit
				// the marker is needed in case the link text appears more than once in the paragraph
				$excerpt = preg_replace('|\</?wpcontext\>|', '', $para);

				// prevent really long link text
				if ( strlen($context[1]) > 100 )
					$context[1] = substr($context[1], 0, 100) . '...';

				$marker = '<wpcontext>'.$context[1].'</wpcontext>';    // set up our marker
				$excerpt= str_replace($context[0], $marker, $excerpt); // swap out the link for our marker
				$excerpt = strip_tags($excerpt, '<wpcontext>');        // strip all tags but our context marker
				$excerpt = trim($excerpt);
				$preg_marker = preg_quote($marker);
				$excerpt = preg_replace("|.*?\s(.{0,100}$preg_marker.{0,100})\s.*|s", '$1', $excerpt);
				$excerpt = strip_tags($excerpt); // YES, again, to remove the marker wrapper
				break;
			}
		}

		if ( empty($context) ) // Link to target not found
			return new IXR_Error(17, __('The source URL does not contain a link to the target URL, and so cannot be used as a source.'));

		$pagelinkedfrom = str_replace('&', '&amp;', $pagelinkedfrom);

		$context = '[...] ' . wp_specialchars( $excerpt ) . ' [...]';
		$pagelinkedfrom = $wpdb->escape( $pagelinkedfrom );

		$comment_post_ID = (int) $post_ID;
		$comment_author = $title;
		$this->escape($comment_author);
		$comment_author_url = $pagelinkedfrom;
		$comment_content = $context;
		$this->escape($comment_content);
		$comment_type = 'pingback';

		$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_url', 'comment_content', 'comment_type');

		$comment_ID = wp_new_comment($commentdata);
		do_action('pingback_post', $comment_ID);

		return sprintf(__('Pingback from %1$s to %2$s registered. Keep the web talking! :-)'), $pagelinkedfrom, $pagelinkedto);
	}

	/**
	 * Retrieve array of URLs that pingbacked the given URL.
	 *
	 * Specs on http://www.aquarionics.com/misc/archives/blogite/0198.html
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function pingback_extensions_getPingbacks($args) {

		global $wpdb;

		do_action('xmlrpc_call', 'pingback.extensions.getPingsbacks');

		$this->escape($args);

		$url = $args;

		$post_ID = url_to_postid($url);
		if (!$post_ID) {
			// We aren't sure that the resource is available and/or pingback enabled
	  		return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));
		}

		$actual_post = wp_get_single_post($post_ID, ARRAY_A);

		if (!$actual_post) {
			// No such post = resource not found
	  		return new IXR_Error(32, __('The specified target URL does not exist.'));
		}

		$comments = $wpdb->get_results( $wpdb->prepare("SELECT comment_author_url, comment_content, comment_author_IP, comment_type FROM $wpdb->comments WHERE comment_post_ID = %d", $post_ID) );

		if (!$comments) {
			return array();
		}

		$pingbacks = array();
		foreach($comments as $comment) {
			if ( 'pingback' == $comment->comment_type )
				$pingbacks[] = $comment->comment_author_url;
		}

		return $pingbacks;
	}
}

$wp_xmlrpc_server = new wp_xmlrpc_server();

?>
