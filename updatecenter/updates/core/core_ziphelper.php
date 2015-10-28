<?php

	$zip_helper_exceptions = array();
	$zip_helper_rename_mapping = array();

	class Core_ZipHelper
	{
		public static $_chmod_error = false;
		
		protected static $_initialized = false;

		public static function findFile($file, $archivePath){
			self::initZip();
			$archive = new PclZip($archivePath);

			foreach($archive->listContent() as $key => $file_info){
				if(strstr($file_info['filename'],$file)){
					return $file_info;
				}

			}
			return false;
		}

		public static function zipFile($file, $archivePath)
		{
			self::initZip();
			chdir(dirname($file));

			$archive = new PclZip($archivePath);
			$res = $archive->create(array());

			$fileName = basename($file);
			$archive->add($fileName);
			@chmod( $archivePath, Phpr_Files::getFilePermissions());
		}
		
		
		/**
		 * Archives a directory
		 * @param $files Array of files to archive relative to $basePath
		 * If keys are non-numeric they will be used as the file name to archive, while
		 * the associated value is used as the name to be used inside the archive
		 */
		public static function zipFiles($basePath, $files, $archivePath)
		{
			global $zip_helper_rename_mapping;
			self::initZip();
			
			chdir($basePath);
			
			$files_to_add = array();
			$zip_helper_rename_mapping = array();
			foreach ($files as $old_path => $new_path)
			{
				if (is_numeric($old_path))
					$old_path = $new_path;
				
				if (!file_exists($old_path))
					continue;
				
				$files_to_add[] = $old_path;
				
				$zip_helper_rename_mapping[$old_path] = $new_path;
			}
			
			$archive = new PclZip($archivePath);
			$res = $archive->create(array());
			
			$archive->add($files_to_add, PCLZIP_CB_PRE_ADD, 'zip_helper_rename_file');
			
			@chmod($archivePath, Phpr_Files::getFilePermissions());
		}
		
		/**
		 * Archives a directory
		 * @param $exceptions Array of folders, files or masks to exclude
		 * Masks could include the following:
		 * images/* - will exclude all folders and files from images directory
		 * images/[files] - file exclude all files from images directory and its subdirectories
		 */
		public static function zipDirectory($path, $archivePath, $exceptions = array(), $archive = null, $add_path = null)
		{
			self::initZip();
			chdir($path);
			
			global $zip_helper_exceptions;
			$zip_helper_exceptions = $exceptions;

			if (!$archive)
			{
				$archive = new PclZip($archivePath);
				$res = $archive->create(array());
			}

			$d = dir($path);
			while ( false !== ($entry = $d->read()) ) 
			{
				if ( $entry == '.' || $entry == '..' )
					continue;

				$archive->add($entry, PCLZIP_CB_PRE_ADD, 'zip_helper_pre_add',PCLZIP_OPT_ADD_PATH, $add_path);
			}

			$d->close();
			@chmod( $archivePath, Phpr_Files::getFilePermissions() );
		}
		
		public static function unzip($path, $archivePath, $no_permissions_override = false)
		{
			if (!file_exists($archivePath))
				throw new Phpr_SystemException('Archive file is not found.');

			if (!is_writable($path))
				throw new Phpr_SystemException('No writing permissions for directory '.$path);

			self::initZip();
			$archive = new PclZip($archivePath);
			if (!$no_permissions_override)
			{
				if (!@$archive->extract(PCLZIP_OPT_PATH, $path, PCLZIP_OPT_REPLACE_NEWER, PCLZIP_CB_POST_EXTRACT, 'zip_helper_post_extract'))
					throw new Phpr_SystemException('Error extracting data from archive.');
			} else
			{
				if (!@$archive->extract(PCLZIP_OPT_PATH, $path, PCLZIP_OPT_REPLACE_NEWER))
					throw new Phpr_SystemException('Error extracting data from archive.');
			}
		}
		
		public static function initZip()
		{
			if (self::$_initialized)
				return;
				
			global $zip_helper_exceptions;
			$zip_helper_exceptions = array();
			
			if (!defined('PATH_INSTALL'))
				require_once(PATH_APP."/modules/core/thirdpart/pclzip.lib.php");

			if ( !defined('PCLZIP_TEMPORARY_DIR') )
			{
				if (!is_writable(PATH_APP.'/temp/'))
					throw new Phpr_SystemException('No writing permissions for directory '.PATH_APP.'/temp');
				
				define( 'PCLZIP_TEMPORARY_DIR', PATH_APP.'/temp/' );
			}
				
			self::$_initialized = true;
		}
	}
	
	function zip_helper_pre_add($p_event, &$p_header)
	{
		global $zip_helper_exceptions;

		$path_parts = pathinfo($p_header['stored_filename']);

		if (
			(
				isset($path_parts['basename']) && 
				(
					$path_parts['basename'] == '.DS_Store' || 
					$path_parts['basename'] == '.svn' || 
					$path_parts['basename'] == '.git' || 
					$path_parts['basename'] == '.gitignore'
					)
			) || 
			(
				strpos($path_parts['dirname'], '.svn') !== false ||
				strpos($path_parts['dirname'], '.git') !== false
			)
		)
			return 0;
			
		$stored_file_name = str_replace('\\', '/', $p_header['stored_filename']);

		foreach ($zip_helper_exceptions as $exception)
		{
			if (substr($exception, -2) == '/*')
			{
				$effective_path = substr($exception, 0, -2);
				$len = strlen($effective_path);

				if ($effective_path == substr($stored_file_name, 0, $len) && substr($stored_file_name, $len, 1) == '/')
					return 0;
			}
			
			if (substr($exception, -8) == '/[files]' && !$p_header['folder'])
			{
				$effective_path = substr($exception, 0, -8);
				$len = strlen($effective_path);

				if ($effective_path == substr($stored_file_name, 0, $len) && substr($stored_file_name, $len, 1) == '/')
					return 0;
			}
			
			if ($exception == $stored_file_name)
				return 0;
		}

		return 1;
	}

	function zip_helper_post_extract($p_event, &$p_header)
	{
		if (file_exists($p_header['filename']))
		{
			$is_folder = array_key_exists('folder', $p_header) ? $p_header['folder'] : false;

			if (!Core_ZipHelper::$_chmod_error)
			{
				$mode = $is_folder ? Phpr_Files::getFolderPermissions() : Phpr_Files::getFilePermissions();
				try
				{
					@chmod($p_header['filename'], $mode);
				} catch (Exception $ex)
				{
					Core_ZipHelper::$_chmod_error = true;
					// throw new Phpr_SystemException('Error setting file permissions to '.$p_header['filename'].'.  Operation not permitted.');
				}
			}
		}

		return 1;
	}
	
	function zip_helper_rename_file($p_event, &$p_header)
	{
		global $zip_helper_rename_mapping;
		
		if (isset($zip_helper_rename_mapping[$p_header['filename']]))
			$p_header['stored_filename'] = $zip_helper_rename_mapping[$p_header['filename']];
		
		return 1;
	}
?>
