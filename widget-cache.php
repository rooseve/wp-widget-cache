<?php
/*
Plugin Name:WP Widget Cache
Plugin URI: https://github.com/rooseve/wp-widget-cache
Description: Cache the output of your blog widgets. Usually it will significantly reduce the sql queries to your database and speed up your site.
Author: Andrew Zhang
Version: 0.26
Author URI: https://github.com/rooseve/wp-widget-cache
*/
require_once (dirname ( __FILE__ ) . "/inc/wcache.class.php");
class WidgetCache
{

	var $plugin_name = 'WP Widget Cache';

	var $plugin_version = '0.26';

	var $wcache;

	var $cachedir;

	var $wgcOptions;

	var $wgcTriggers;

	var $wgcSettings;

	var $wgcVaryParams;

	var $wgcEnabled = true;

	var $wgcAutoExpireEnabled = true;

	var $wgcVaryParamsEnabled = false;

	var $triggerActions = array ();

	var $varyParams = array ();

	function WidgetCache()
	{
		$this->cachedir = WP_CONTENT_DIR . '/widget-cache';
		
		$url_info = parse_url ( site_url () );
		
		$shost = $this->array_element ( $url_info, 'host' );
		$spath = $this->array_element ( $url_info, 'path' );
		
		//maybe got many blogs under the same source
		$this->cachedir .= '/' . $shost . ($spath ? '_' . md5 ( $spath ) : '');
		
		$this->wcache = new WCache ( $this->cachedir );
		
		if (! (is_dir ( $this->cachedir ) && is_writable ( $this->cachedir )))
		{
			add_action ( 'admin_notices', array (
					&$this,
					'widget_cache_warning' 
			) );
		}
		else
		{
			$this->__wgc_load_opts ();
			
			if ($this->wgcEnabled)
			{
				if ($this->wgcAutoExpireEnabled)
				{
					$this->triggerActions = array (
							"category" => array (
									"add_category",
									"create_category",
									"edit_category",
									"delete_category" 
							),
							"comment" => array (
									"comment_post",
									"edit_comment",
									"delete_comment",
									"pingback_post",
									"trackback_post",
									"wp_set_comment_status" 
							),
							"link" => array (
									"add_link",
									"edit_link",
									"delete_link" 
							),
							"post" => array (
									"delete_post",
									"save_post" 
							),
							"tag" => array (
									"create_term",
									"edit_term",
									"delete_term" 
							) 
					);
				}
				if ($this->wgcVaryParamsEnabled)
				{
					$this->varyParams = array (
							"userLevel" => array (
									&$this,
									'get_user_level' 
							),
							"userAgent" => array (
									&$this,
									'get_user_agent' 
							),
							"currentCategory" => array (
									&$this,
									'get_current_category' 
							) 
					);
				}
				add_action ( 'wp_head', array (
						&$this,
						'widget_cache_redirect_callback' 
				), 99999 );
			}
			
			if (is_admin ())
			{
				add_action ( 'admin_menu', array (
						&$this,
						'wp_add_options_page' 
				) );
				add_action ( 'dashmenu', array (
						&$this,
						'dashboard_delete_wg_cache' 
				) );
				
				if ($this->wgcEnabled)
				{
					add_action ( 'sidebar_admin_page', 
							array (
									&$this,
									'widget_cache_options_filter' 
							) );
				}
				
				add_action ( 'sidebar_admin_setup', 
						array (
								&$this,
								'widget_cache_expand_control' 
						) );
				
				if (isset ( $_GET ["wgdel"] ))
				{
					add_action ( 'admin_notices', array (
							&$this,
							'widget_wgdel_notice' 
					) );
				}
			}
		}
	}

	function dashboard_delete_wg_cache()
	{
		if (function_exists ( 'is_site_admin' ) && ! is_site_admin ())
			return false;
		if (function_exists ( 'current_user_can' ) && ! current_user_can ( 'manage_options' ))
			return false;
		echo "<li><a href='" . wp_nonce_url ( 'options-general.php?page=widget-cache.php&clear=1', 'widget-cache' ) .
				 "' title='Clear widget cache'>Clear widget cache</a></li>";
	}

	function wgc_get_option($key, $default = array ())
	{
		$ops = get_option ( $key );
		
		if (! $ops)
		{
			$ops = $default;
		}
		else
		{
			foreach ( $default as $k => $v )
			{
				if (! isset ( $ops [$k] ))
					$ops [$k] = $v;
			}
		}
		
		return $ops;
	}

	function array_element($array, $ele)
	{
		return isset ( $array [$ele] ) ? $array [$ele] : false;
	}

	function __wgc_load_opts()
	{
		$this->wgcSettings = $this->wgc_get_option ( "widget_cache_settings", 
				array (
						'wgc_disabled' => 0,
						'wgc_ae_ops_disabled' => 0,
						'wgc_vary_by_params_enabled' => 0 
				) );
		
		$this->wgcOptions = $this->wgc_get_option ( 'widget_cache' );
		$this->wgcTriggers = $this->wgc_get_option ( 'widget_cache_action_trigger' );
		$this->wgcVaryParams = $this->wgc_get_option ( 'widget_cache_vary_param' );
		
		$this->wgcEnabled = $this->wgcSettings ["wgc_disabled"] != "1";
		
		$this->wgcAutoExpireEnabled = $this->wgcSettings ["wgc_ae_ops_disabled"] != "1";
		
		$this->wgcVaryParamsEnabled = $this->wgcSettings ["wgc_vary_by_params_enabled"] == "1";
	}

	function wgc_update_option($key, $value)
	{
		update_option ( $key, $value );
	}

	function wp_load_default_settings()
	{
		$wgc_ops = array ();
		$this->wgc_update_option ( 'widget_cache_settings', $wgc_ops );
		return $wgc_ops;
	}

	function wp_add_options_page()
	{
		if (function_exists ( 'add_options_page' ))
		{
			add_options_page ( $this->plugin_name, $this->plugin_name, 'manage_options', basename ( __FILE__ ), 
					array (
							&$this,
							'wp_options_subpanel' 
					) );
		}
	}

	function wp_options_subpanel()
	{
		if (isset ( $_POST ["widget_cache-clear"] ) || isset ( $_GET ['clear'] ) && $_GET ['clear'] == "1")
		{
			$this->wcache->clear ();
			echo '<div id="message" class="updated fade"><p>Cache Cleared</p></div>';
		}
		
		if (isset ( $_POST ["wp_wgc_submit"] ))
		{
			$wp_settings = array (
					"wgc_disabled" => $this->array_element ( $_POST, 'wgc_enabled' ) ? false : "1",
					"wgc_ae_ops_disabled" => $this->array_element ( $_POST, 'wgc_ae_ops_enabled' ) ? false : "1",
					"wgc_vary_by_params_enabled" => $this->array_element ( $_POST, 'wgc_vary_by_params_enabled' ) ? '1' : false 
			);
			$this->wgc_update_option ( "widget_cache_settings", $wp_settings );
			echo '<div id="message" class="updated fade"><p>Options Updated</p></div>';
		}
		else if (isset ( $_POST ["wp_wgc_load_default"] ))
		{
			$wp_settings = $this->wp_load_default_settings ();
			echo '<div id="message" class="updated fade"><p>Options Reset</p></div>';
		}
		else
		{
			$wp_settings = $this->wgcSettings;
		}
		?>
<div class="wrap">
	<form
		action="<?php echo $_SERVER['PHP_SELF']; ?>?page=widget-cache.php"
		method="post">
		<h2><?php echo $this->plugin_name;?> Options</h2>
		<table class="form-table">
			<tr valign="top">
				<td><input name="wgc_enabled" type="checkbox" id="wgc_enabled"
					value="1"
					<?php checked('1', !($this->array_element ($wp_settings, "wgc_disabled")=="1")); ?> />
					<label for=wgc_enabled><strong>Enable Widget Cache</strong> </label></td>
			</tr>
			<tr valign="top">
				<td><input name="wgc_ae_ops_enabled" type="checkbox"
					id="wgc_ae_ops_enabled" value="1"
					<?php checked('1', !($this->array_element ($wp_settings, "wgc_ae_ops_disabled")=="1")); ?> />
					<label for=wgc_ae_ops_enabled><strong>Enable auto expire options
							(e.g. When categories, comments, posts, tags changed)</strong> </label>
				</td>
			</tr>
			<tr valign="top">
				<td><input name="wgc_vary_by_params_enabled" type="checkbox"
					id="wgc_vary_by_params_enabled" value="1"
					<?php checked('1', ($this->array_element ($wp_settings, "wgc_vary_by_params_enabled")=="1")); ?> />
					<label for=wgc_vary_by_params_enabled><strong>Enable vary by params
							options (e.g. Vary by user levels, user agents)</strong> </label>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="wp_wgc_load_default"
				value="Reset to Default Options &raquo;" class="button"
				onclick="return confirm('Are you sure to reset options?')" /> <input
				type="submit" name="wp_wgc_submit" value="Save Options &raquo;"
				class="button" style="margin-left: 15px;" />
		</p>
		<p>
			<input type="submit" name="widget_cache-clear" class="button"
				value="Clear all widgets cache(<?php echo $this->wcache->cachecount(); ?>)" />
		</p>
	</form>
</div>
<?php
	}

	function widget_cache_warning()
	{
		$pdir = WP_CONTENT_DIR . '/';
		
		if (is_dir ( $this->cachedir ))
		{
			$wmsg = "'$this->cachedir' is not writable, please check '$this->cachedir' permissions, and give your web server the permission to create directory and file.";
		}
		else
		{
			$wmsg = "'$this->cachedir' cann't be created, please check '$pdir' permissions, and give your web server the permission to create directory and file.";
		}
		
		echo "<div id='widget-cache-warning' class='updated fade'><p><strong>WP Widget Cache not work!</strong><br/>$wmsg</p></div>";
	}

	function widget_wgdel_notice()
	{
		$id = $_GET ["wgdel"];
		$this->wcache->remove_group ( $id );
		echo '<div id="widget-cache-notice" class="updated fade"><p>Delete widget cache: ' . $id . '</p></div>';
	}

	function widget_cache_options_filter()
	{
		$wl_options = $this->wgcOptions;
		?>
<div class="wrap">
	<form method="POST">
		<a name="wgcoptions"></a>
		<h2><?php  echo $this->plugin_name; ?> Options</h2>
		<p style="line-height: 30px;">
			<span class="submit"> <input type="submit" name="widget_cache-clear"
				class="button" id="widget_cache-options-submit"
				value="Clear all widgets cache(<?php echo $this->wcache->cachecount(); ?>)" />
			</span>
		</p>
	</form>
</div>
<?php
	}

	function widget_cache_expand_control()
	{
		global $wp_registered_widgets, $wp_registered_widget_controls;
		
		$wc_options = $this->wgcOptions;
		$wc_trigers = $this->wgcTriggers;
		$wc_varyparams = $this->wgcVaryParams;
		
		if ($this->wgcEnabled)
		{
			foreach ( $wp_registered_widgets as $id => $widget )
			{
				if (! $wp_registered_widget_controls [$id])
					wp_register_widget_control ( $id, $widget ['name'], 
							array (
									&$this,
									'widget_cache_empty_control' 
							) );
				
				if (! array_key_exists ( 0, $wp_registered_widget_controls [$id] ['params'] ) ||
						 is_array ( $wp_registered_widget_controls [$id] ['params'] [0] ))
					$wp_registered_widget_controls [$id] ['params'] [0] ['id_for_wc'] = $id;
				else
				{
					array_push ( $wp_registered_widget_controls [$id] ['params'], $id );
					$wp_registered_widget_controls [$id] ['height'] += 40;
				}
				
				$wp_registered_widget_controls [$id] ['callback_wc_redirect'] = $wp_registered_widget_controls [$id] ['callback'];
				$wp_registered_widget_controls [$id] ['callback'] = array (
						&$this,
						'widget_cache_extra_control' 
				);
			}
			
			if ('post' == strtolower ( $_SERVER ['REQUEST_METHOD'] ))
			{
				if (isset ( $_POST ["widget_cache-clear"] ))
				{
					$this->wcache->clear ();
					wp_redirect ( add_query_arg ( 'message', 'wgc#wgcoptions' ) );
					exit ();
				}
				
				foreach ( ( array ) $_POST ['widget-id'] as $widget_number => $widget_id )
				{
					if (isset ( $_POST [$widget_id . '-wgc-expire'] ))
					{
						$wc_options [$widget_id] = intval ( $_POST [$widget_id . '-wgc-expire'] );
					}
					if ($this->wgcAutoExpireEnabled)
					{
						if (isset ( $_POST [$widget_id . '-wgc-trigger'] ))
						{
							$wc_trigers [$widget_id] = ($_POST [$widget_id . '-wgc-trigger']);
						}
						else
						{
							unset ( $wc_trigers [$widget_id] );
						}
					}
					if ($this->wgcVaryParamsEnabled)
					{
						if (isset ( $_POST [$widget_id . '-widget_cache-varyparam'] ))
						{
							$wc_varyparams [$widget_id] = ($_POST [$widget_id . '-widget_cache-varyparam']);
						}
						else
						{
							unset ( $wc_varyparams [$widget_id] );
						}
					}
				}
				
				$regd_plus_new = array_merge ( array_keys ( $wp_registered_widgets ), 
						array_values ( ( array ) $_POST ['widget-id'] ) );
				foreach ( array_keys ( $wc_options ) as $key )
				{
					if (! in_array ( $key, $regd_plus_new ))
					{
						unset ( $wc_options [$key] );
						if ($this->wgcAutoExpireEnabled)
							unset ( $wc_trigers [$key] );
						if ($this->wgcVaryParamsEnabled)
							unset ( $wc_varyparams [$key] );
					}
				}
				
				$this->wgc_update_option ( 'widget_cache', $wc_options );
				
				if ($this->wgcAutoExpireEnabled)
					$this->wgc_update_option ( 'widget_cache_action_trigger', $wc_trigers );
				
				if ($this->wgcVaryParamsEnabled)
					$this->wgc_update_option ( 'widget_cache_vary_param', $wc_varyparams );
				
				$this->__wgc_load_opts ();
			}
		}
	}

	function widget_cache_empty_control()
	{
	}

	function widget_cache_extra_control()
	{
		global $wp_registered_widget_controls;
		$params = func_get_args ();
		
		$id = (is_array ( $params [0] )) ? $params [0] ['id_for_wc'] : array_pop ( $params );
		
		$id_disp = $id;
		
		if ($this->wgcEnabled) // WP Widget Cache enabled
		{
			$wc_options = $this->wgcOptions;
			
			$value = $this->array_element ( $wc_options, $id );
			
			if (is_array ( $params [0] ) && isset ( $params [0] ['number'] ))
			{
				$number = $params [0] ['number'];
				
				if ($number == - 1)
				{
					$number = "%i%";
					$value = "";
				}
				
				$id_disp = $wp_registered_widget_controls [$id] ['id_base'] . '-' . $number;
			}
			
			$value = intval ( $value );
			if ($value <= 0)
				$value = "";
			
			$this->output_widget_options_panel ( $id_disp, $value );
		}
		else
		{
			echo '<label style="color: gray; font-style: italic; margin-bottom: 10px; line-height: 150%;">WP Widget Cache disabled</label>';
		}
		
		$callback = $wp_registered_widget_controls [$id] ['callback_wc_redirect'];
		if (is_callable ( $callback ))
		{
			call_user_func_array ( $callback, $params );
		}
	}

	function output_widget_options_panel($id_disp, $expire_ts)
	{
		?>
<fieldset
	style="border: 1px solid #2583AD; padding: 3px 0 3px 5px; margin-bottom: 10px; line-height: 150%;">
	<legend><?php echo $this->plugin_name; ?></legend>
	<div>
		Expire in <input type='text' name='<?php echo $id_disp; ?>-wgc-expire'
			id='<?php echo $id_disp; ?>-wgc-expire'
			value='<?php echo $expire_ts; ?>' size=6 style="padding: 0" />
		second(s) <br />(Left empty means no cache) <br /> <a
			href='widgets.php?wgdel=<?php echo urlencode($id_disp) ?>'>Delete
			cache of this widget</a>
	</div>
  <?php if ($this->wgcAutoExpireEnabled): ?>
  <div style="margin-top: 5px; border-top: 1px solid #ccc; clear: both;">
		<div>Auto expire when these things changed:</div>
    <?php foreach ( $this->triggerActions as $tkey => $actArr ): ?>
    <?php
				$checked = "";
				if ($this->array_element ( $this->wgcTriggers, $id_disp ) &&
						 in_array ( $tkey, $this->wgcTriggers [$id_disp] ))
				{
					$checked = "checked=\"checked\"";
				}
				?>
    <div style="float: left; display: inline; margin: 2px 1px 1px 0;"
			nowrap="nowrap">
			<input type="checkbox" <?php echo $checked;?>
				id="<?php echo $id_disp; ?>-wgc-trigger-<?php echo $tkey;?>"
				name="<?php echo $id_disp; ?>-wgc-trigger[]"
				value="<?php echo $tkey;?>" /> <label
				for="<?php echo $id_disp; ?>-wgc-trigger-<?php echo $tkey;?>"><?php echo ucwords($tkey);?></label>
		</div>
    <?php endforeach; ?>
  </div>
  <?php  endif; ?>
  <?php if ($this->wgcVaryParamsEnabled): ?>
  <div style="margin-top: 5px; border-top: 1px solid #ccc; clear: both;">
		<div>Vary by:</div>
    <?php foreach ( $this->varyParams as $vparam => $vfunc ): ?>
    <?php
				$checked = "";
				if ($this->array_element ( $this->wgcVaryParams, $id_disp ) &&
						 in_array ( $vparam, $this->wgcVaryParams [$id_disp] ))
				{
					$checked = "checked=\"checked\"";
				}
				?>
    <div style="float: left; display: inline; margin: 2px 1px 1px 0;"
			nowrap="nowrap">
			<input type="checkbox" <?php echo $checked;?>
				id="<?php echo $id_disp; ?>-widget_cache-varyparam-<?php echo $vparam;?>"
				name="<?php echo $id_disp; ?>-widget_cache-varyparam[]"
				value="<?php echo $vparam;?>" /> <label
				for="<?php echo $id_disp; ?>-widget_cache-varyparam-<?php echo $vparam;?>"><?php echo ucwords($vparam);?></label>
		</div>
    <?php endforeach; ?>
  </div>
  <?php endif;?>
</fieldset>
<?php
	}

	function widget_cache_redirect_callback()
	{
		global $wp_registered_widgets;
		foreach ( $wp_registered_widgets as $id => $widget )
		{
			array_push ( $wp_registered_widgets [$id] ['params'], $id );
			$wp_registered_widgets [$id] ['callback_wc_redirect'] = $wp_registered_widgets [$id] ['callback'];
			$wp_registered_widgets [$id] ['callback'] = array (
					&$this,
					'widget_cache_redirected_callback' 
			);
		}
	}

	function get_user_level()
	{
		global $current_user;
		
		get_currentuserinfo ();
		return $current_user->wp_user_level;
	}

	function get_user_agent()
	{
		return $_SERVER ['HTTP_USER_AGENT'];
	}

	function get_current_category()
	{
		if (is_single ())
		{
			global $post;
			$categories = get_the_category ( $post->ID );
			$cidArr = array ();
			foreach ( $categories as $category )
			{
				$cidArr [] = $category->cat_ID;
			}
			return join ( ",", $cidArr );
		}
		elseif (is_category ())
		{
			return get_query_var ( 'cat' );
		}
		
		return false;
	}

	function get_widget_cache_key($id)
	{
		$wckey = "wgcache_" . $id;
		
		if ($this->wgcVaryParamsEnabled && isset ( $this->wgcVaryParams [$id] ))
		{
			foreach ( $this->wgcVaryParams [$id] as $vparam )
			{
				if ($this->varyParams [$vparam])
				{
					if (is_callable ( $this->varyParams [$vparam] ))
					{
						$temv = call_user_func ( $this->varyParams [$vparam] );
						if ($temv)
							$wckey .= "_" . $temv;
					}
				}
			}
		}
		
		return $wckey;
	}

	function widget_cache_redirected_callback()
	{
		global $wp_registered_widgets;
		
		// get all the passed params
		$params = func_get_args ();
		
		// take off the widget ID
		$id = array_pop ( $params );
		
		$callback = $wp_registered_widgets [$id] ['callback_wc_redirect']; // find the real callback
		

		if (! is_callable ( $callback ))
			return;
		
		$wc_options = $this->wgcOptions;
		
		$expire_ts = isset ( $wc_options [$id] ) ? intval ( $wc_options [$id] ) : - 1;
		
		if ($expire_ts > 0)
		{
			echo "<!--$this->plugin_name $this->plugin_version Begin -->\n";
			
			echo "<!--Cache $id for $expire_ts second(s)-->\n";
			
			while ( $this->wcache->save ( $this->get_widget_cache_key ( $id ), $expire_ts, null, $id ) )
			{
				call_user_func_array ( $callback, $params );
			}
			
			echo "<!--$this->plugin_name End -->\n";
		}
		else
		{
			call_user_func_array ( $callback, $params );
		}
	}

}

$widget_cache = new WidgetCache ();

function widget_cache_remove($id)
{
	global $widget_cache;
	$widget_cache->wcache->remove_group ( $id );
}

function widget_cache_hook_trigger()
{
	global $widget_cache;
	if (isset ( $widget_cache->wgcTriggers ) && $widget_cache->wgcTriggers)
	{
		foreach ( $widget_cache->wgcTriggers as $wgid => $wgacts )
		{
			foreach ( $wgacts as $wact )
			{
				if (isset ( $widget_cache->triggerActions [$wact] ))
				{
					foreach ( $widget_cache->triggerActions [$wact] as $wpaction )
					{
						add_action ( $wpaction, 
								create_function ( '$actionarg', 'widget_cache_remove("' . addslashes ( $wgid ) . '");' ), 
								10, 1 );
					}
				}
			}
		}
	}
}

if ($widget_cache->wgcAutoExpireEnabled)
{
	widget_cache_hook_trigger ();
}