<?php
class WCache
{

	var $dir_mode = 0755;

	var $surephp5 = false;

	var $fld_version = '__wgc_v';

	var $fld_output = 'output';

	var $fld_data = 'data';

	/**
	 *
	 * @param string $path,
	 *        	the cache dir path
	 * @param string $disable
	 *        	disable the cache or not
	 * @param string $disable_output
	 *        	disable track the output(ob_start) or not
	 */
	function WCache($cache_dir, $disable = false, $disable_output = false)
	{
		if (function_exists ( "version_compare" ))
		{
			$this->surephp5 = version_compare ( PHP_VERSION, '5.0.0', '>=' );
		}
		
		//make cache dir
		if (! is_dir ( $cache_dir ))
		{
			$this->__do_mkdir ( $cache_dir, $this->dir_mode );
			$disable = ! is_dir ( $cache_dir );
		}
		
		//writable or not
		if (! $disable && ! is_writable ( $cache_dir ))
		{
			@chmod ( $cache_dir, $this->dir_mode );
			$disable = ! is_writable ( $cache_dir );
		}
		
		//make sure path ends with /
		if (! in_array ( substr ( $cache_dir, - 1 ), array (
				"\\",
				"/" 
		) ))
		{
			$cache_dir .= "/";
		}
		
		$this->path = $cache_dir;
		$this->disable = $disable;
		$this->disable_output = $disable_output;
		$this->stack = array ();
		$this->output = null;
	}

	/**
	 * Use like this:
	 *
	 * <code>
	 * while ( save(key, 3000, &$data, $groupA) )
	 * {
	 * //echo something
	 * //set $data, e.g. $data['k'] = 32142314;
	 * }
	 *
	 * //here you got the $data
	 * </code>
	 *
	 * @param string $key
	 *        	the cache key
	 * @param int $expire_timespan
	 *        	how long will be expired, in seconds
	 * @param &array $cdata，
	 *        	normally will be a reference
	 * @param string $group
	 *        	cache group, so you can remove all caches in some group
	 * @return boolean
	 */
	function save($key, $expire_timespan, $cdata = null, $group = false)
	{
		if ($this->disable)
		{
			//nothing to do
			return false;
		}
		
		$keypath = $this->__get_key_path ( $key, $group );
		
		$expire_timespan = max ( 3, intval ( $expire_timespan ) );
		
		//here the real data created
		if (count ( $this->stack ) && $keypath == $this->stack [count ( $this->stack ) - 1])
		{
			$ob_output = false;
			
			if (! $this->disable_output)
			{
				$ob_output = ob_get_contents ();
				ob_end_clean ();
				
				$this->__echo_output ( $ob_output );
			}
			
			//create a cache pack
			$cpack = array ();
			$cpack [$this->fld_version] = 2;
			$cpack [$this->fld_output] = $ob_output;
			$cpack [$this->fld_data] = $cdata;
			
			$this->__save_cache ( $keypath, $cpack );
			
			unset ( $this->stack [count ( $this->stack ) - 1] );
			
			return false;
		}
		elseif (count ( $this->stack ) && in_array ( $keypath, $this->stack ))
		{
			trigger_error ( 
					"Cache stack problem: " . $this->stack [count ( $this->stack ) - 1] . " not properly finished!", 
					E_USER_ERROR );
			return false;
		}
		else
		{
			$res = $this->__start_track ( $keypath, $expire_timespan );
			
			//well no cache available
			if (is_int ( $res ))
			{
				if (! $this->disable_output)
				{
					//track the output
					ob_start ();
				}
				
				return $res;
			}
			else
			{
				//old version cache data
				if (! isset ( $res [$this->fld_version] ))
				{
					$res [$this->fld_version] = 1;
				}
				
				$res_output = false;
				$res_cdata = array ();
				
				switch ($res [$this->fld_version])
				{
					case 1 :
						
						if (isset ( $res ['__output__'] ))
						{
							$res_output = $res ['__output__'];
							unset ( $res ['__output__'] );
						}
						
						$res_cdata = $res;
						
						break;
					
					default :
						
						$res_output = $res [$this->fld_output];
						$res_cdata = $res [$this->fld_data];
						
						break;
				}
				
				$this->__echo_output ( $res_output );
				
				if (is_array ( $cdata ))
				{
					//copy the cdata
					foreach ( $res_cdata as $k => $v )
					{
						$cdata [$k] = $res_cdata [$v];
					}
				}
				
				return false;
			}
		}
	}

	/**
	 * Remove all caches
	 *
	 * @param number $expire_timespan        	
	 * @return number
	 */
	function clear($expire_timespan = 0)
	{
		return $this->__scan_dir ( $this->path, $expire_timespan );
	}

	/**
	 * Remove a single cache
	 *
	 * @param string $key        	
	 * @param string $group        	
	 */
	function remove($key, $group = false)
	{
		if (! $key)
		{
			return;
		}
		
		$keypath = $this->__get_key_path ( $key, $group );
		
		$this->__remove_cache ( $keypath );
	}

	/**
	 * Remove caches in a group
	 *
	 * @param string $group        	
	 */
	function remove_group($group)
	{
		if (! $group)
		{
			return;
		}
		
		$subdir = $this->__encode_key ( $group );
		
		$this->__remove_cache ( $subdir );
	}

	/**
	 * How many caches in the cache dir
	 *
	 * @return number
	 */
	function cachecount()
	{
		return $this->__scan_dir ( $this->path, - 100 );
	}

	function __echo_output($output)
	{
		$this->output = $output;
		
		if (! $this->disable_output)
		{
			echo $output;
		}
	}

	function __remove_cache($keypath)
	{
		$filename = $this->path . $keypath;
		
		if (is_file ( $filename ))
		{
			@unlink ( $filename );
			return true;
		}
		else if (is_dir ( $filename ))
		{
			$this->__scan_dir ( $filename, 0 );
		}
		
		return false;
	}

	function __save_cache($keypath, $data)
	{
		if ($this->disable)
		{
			return false;
		}
		
		$filename = $this->path . $keypath;
		
		if (file_exists ( $filename ) && ! is_writable ( $filename ))
		{
			trigger_error ( "Cache file not writeable!", E_USER_ERROR );
			return false;
		}
		
		$f = fopen ( $filename, 'w' );
		if (flock ( $f, LOCK_EX ))
		{
			fwrite ( $f, $this->__unpack_data ( $data ) );
			flock ( $f, LOCK_UN );
		}
		fclose ( $f );
		
		return true;
	}

	function __load_cache($keypath, $expire_timespan)
	{
		if ($this->disable)
		{
			return false;
		}
		
		$filename = $this->path . $keypath;
		
		if (! file_exists ( $filename ))
		{
			return false;
		}
		
		if (time () - filemtime ( $filename ) > $expire_timespan)
		{
			return false;
		}
		
		return @file_get_contents ( $filename );
	}

	function __start_track($keypath, $time)
	{
		$data = $this->__load_cache ( $keypath, $time );
		
		//no cache available
		if ($data === false)
		{
			//push it to stack
			$this->stack [count ( $this->stack )] = $keypath;
			
			return count ( $this->stack );
		}
		
		$data = $this->__pack_data ( $data );
		
		return $data;
	}

	function __pack_data($data)
	{
		return unserialize ( $data );
	}

	function __unpack_data($data)
	{
		return serialize ( $data );
	}

	function __encode_key($name)
	{
		return md5 ( $name );
	}

	function __get_key_path($key, $group = false)
	{
		if (! is_string ( $key ))
		{
			$key = serialize ( $key );
		}
		
		$key = $this->__encode_key ( $key );
		
		if ($group)
		{
			if (! is_string ( $group ))
				$group = serialize ( $group );
			
			$subdir = $this->__encode_key ( $group );
			
			if (! is_dir ( $this->path . $subdir ))
			{
				$this->__do_mkdir ( $this->path . $subdir, $this->dir_mode );
			}
			
			$key = $subdir . "/" . $key;
		}
		
		return $key;
	}

	function __mkdir_recursive($pathname, $mode)
	{
		is_dir ( dirname ( $pathname ) ) || $this->__mkdir_recursive ( dirname ( $pathname ), $mode );
		
		return is_dir ( $pathname ) || @mkdir ( $pathname, $mode );
	}

	function __do_mkdir($pathname, $mode)
	{
		if ($this->surephp5)
			@mkdir ( $pathname, $mode, true );
		else
			$this->__mkdir_recursive ( $pathname, $mode );
	}

	function __scan_dir($dir, $expire_timespan = 0)
	{
		$n = 0;
		$dirstack = array ();
		array_push ( $dirstack, $dir );
		do
		{
			$dir = array_pop ( $dirstack );
			if (! in_array ( substr ( $dir, - 1 ), array (
					"\\",
					"/" 
			) ))
			{
				$dir .= "/";
			}
			
			$fs = @scandir ( $dir );
			foreach ( $fs as $f )
			{
				if (in_array ( $f, array (
						".",
						".." 
				) ))
				{
					continue;
				}
				
				$fn = $dir . $f;
				if (! is_readable ( $fn ))
				{
					continue;
				}
				
				if (is_file ( $fn ))
				{
					if ($expire_timespan > 0)
					{
						$ts = time () - filemtime ( $fn );
						if ($ts < $expire_timespan)
						{
							continue;
						}
					}
					
					if ($expire_timespan >= 0)
					{
						@unlink ( $fn );
					}
					
					$n ++;
				}
				elseif (is_dir ( $fn ))
				{
					array_push ( $dirstack, $fn );
				}
			}
			if ($expire_timespan == 0)
			{
				@rmdir ( $dir );
			}
		} while ( sizeof ( $dirstack ) > 0 );
		
		return $n;
	}

}
