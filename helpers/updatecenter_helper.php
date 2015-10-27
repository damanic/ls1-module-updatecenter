<?php
class updateCenter_Helper {

	public static function repackage_archive($module_name,$archiveFile,$archiveFolder){
		$new_zip = PATH_APP.'/temp/'.$module_name.'.zip';
		$temp_dir = PATH_APP.'/temp/uc_'.$module_name.'_repack/';
		if(!is_dir($temp_dir) && !mkdir($temp_dir)){
			throw new Phpr_ApplicationException('Please update folder permissions. PHP cannot create directory in '.PATH_APP.'/temp/');
		}
		Core_ZipHelper::unzip($temp_dir, $archiveFile);
		Core_ZipHelper::zipDirectory($temp_dir.$archiveFolder,$new_zip,array(),null,'modules/'.$module_name);
		if(is_dir($temp_dir)){
			Phpr_Files::removeDirRecursive($temp_dir);
		}
		return $new_zip;
	}

	public function is_old_ls_version($module_name,$version){

		$current_version = $version;
		$last_ls_version = UpdateCenter_Config::get_last_ls_version($module_name);

		return self::is_version_newer($last_ls_version, $current_version);
	}


	public static function is_version_newer($new_version, $old_version){
		if(!strstr($old_version,'.')){
			return true;
		}

		$new_version_array = explode('.',$new_version);
		$old_version_array = explode('.', $old_version);

		if(count($new_version_array) !== 3){
			throw new Phpr_ApplicationException('Update center module requires update versions use simple "PHP-standardized" version numbering. Eg.  1.0.0');
		}

		return version_compare($new_version, $old_version, '>');
	}


}