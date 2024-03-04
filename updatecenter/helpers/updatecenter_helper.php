<?php
class updateCenter_Helper {

	public static function repackage_archive($module_name,$archiveFile,$archiveFolder){
		$new_zip = PATH_APP.'/temp/'.$module_name.'.zip';
		$temp_dir = PATH_APP.'/temp/uc_'.$module_name.'_repack/';
		if(!is_dir($temp_dir) && !mkdir($temp_dir)){
			throw new Phpr_ApplicationException('Please update folder permissions. PHP cannot create directory in '.PATH_APP.'/temp/');
		}
		Core_ZipHelper::unzip($temp_dir, $archiveFile);
        try {
            @unlink($archiveFile);
        } catch (Exception $e) {
            traceLog('Could not delete original archive file: '.$archiveFile);
        }
		Core_ZipHelper::zipDirectory($temp_dir.$archiveFolder,$new_zip,array(),null,'modules/'.$module_name);
		if(is_dir($temp_dir)){
			Phpr_Files::removeDirRecursive($temp_dir);
		}
		return realpath($new_zip);
	}

	public static function is_old_ls_version($module_name,$version){

		$current_version = $version;
		$last_ls_version = UpdateCenter_Config::get_last_ls_version($module_name);

		return self::is_version_newer($last_ls_version, $current_version);
	}


	public static function is_version_newer($new_version, $old_version){

		$new_version = empty($new_version) ? '0.0.0' : $new_version;
		$old_version = empty($old_version) ? '0.0.0' : $old_version;

		$new_version_array = explode('.',$new_version);
		$old_version_array = explode('.', $old_version);

		if(count($new_version_array) !== 3){
			throw new Phpr_ApplicationException('Update center module requires update versions use simple "PHP-standardized" version numbering. Eg.  1.0.0');
		}

		return version_compare($new_version, $old_version, '>');
	}

	public static function check_core_compatible(){
		$core_um_location = PATH_APP.'/modules/core/classes/core_updatemanager.php';
		$core_um_file_content = file_get_contents($core_um_location);
		$pclzip_lib_location = PATH_APP.'/modules/core/thirdpart/pclzip.lib.php';
		$pclzip_lib_file_content = file_get_contents($pclzip_lib_location);

		$required_core_code = array(
			'function get_blocked_update_modules(',
			'core:onAfterGetModuleVersions',
			'core:onGetBlockedUpdateModules',
			'core:onAfterRequestUpdateList',
			'function request_lemonstand_update_list(',
			'core:onFetchSoftwareUpdateFiles'
		);



		if( $core_um_file_content === false || $pclzip_lib_file_content === false) {
			throw new Phpr_ApplicationException('Could not read contents of required files in the core module. Please check files exists and is available to file_get_contents(): '.$core_um_location,' | '.$pclzip_lib_location);
		}

		foreach($required_core_code as $string){
			if(strpos($core_um_file_content, $string) === false){
				return false;
			}
		}

		if(strpos($core_um_file_content, $string) === false){
			return false;
		}


		return true;
	}

	public static function are_modules_writable($dir=null){
		$dir = empty($dir) ? PATH_APP.'/modules' : $dir;
		$not_writable = array();
		if (is_dir($dir)) {
			if(is_writable($dir)){
				$objects = scandir($dir);
				foreach ($objects as $object) {
					if ($object != "." && $object != ".." && substr($object, 0, 1) != '.') {
						if (!self::are_modules_writable($dir."/".$object)){
							$not_writable[] = $dir."/".$object;
						}
					}
				}
			} else {
				$not_writable[] = $dir;
			}

		} else if(file_exists($dir)){
			if (!is_writable($dir)){
				$not_writable[] = $dir;
			}
		}

		if(count($not_writable)){
			foreach($not_writable as $location){
				traceLog('Update Center Notice: PHP does not have permission to write to '.$location);
			}
			return false;
		}
		return true;
	}

}