<?

class updateCenter_Module extends Core_ModuleBase {


	protected function createModuleInfo() {
		return new Core_ModuleInfo(
			"Update Center",
			"Alternative update options",
			"Matt Manning (github:damanic)"
		);
	}

	public function listSettingsItems()
	{
		$result = array(
			array(
				'icon'=>'/modules/updatecenter/resources/images/fork.png',
				'title'=>'Custom Updates',
				'url'=>'/updatecenter/setup',
				'description'=>'Allow modules to be updated from public repositories or block modules from being updated by lemonstand',
				'sort_id'=>250,
				'section'=>'System'
			),
		);


		return $result;
	}

	/*
	 * Access points
	 */
	public function register_access_points(){}

	public function subscribeEvents(){
		Backend::$events->addEvent('core:onAfterGetModuleVersions', $this, 'override_ls_versions');
		Backend::$events->addEvent('core:onGetBlockedUpdateModules', $this, 'get_blocked_updates');
		Backend::$events->addEvent('core:onAfterRequestUpdateList', $this, 'add_repository_updates');
		Backend::$events->addEvent('core:onFetchSoftwareUpdateFiles',$this, 'add_repository_update_files');
	}

	public function add_repository_update_files($data){
		$config = UpdateCenter_Config::get();
		if(!$config->has_active_repository()){
			return $data;
		}

		$repo = new UpdateCenter_Repository();
		$updates = $repo->get_repository_updates();
		foreach($updates as $module_name => $obj){
			$file = $repo->download_update_to_temp($module_name);
			$has_version = Core_ZipHelper::findFile('updates/version.dat', $file);
			if($has_version){
				if($has_version['filename'] == 'modules/'.$module_name.'/updates/version.dat') {
					//archive is in correct folder structure, pass directly to update
					$data['files'][] = $file;
				} else {
					//archive needs to be repackaged
					$root_folder = str_replace('updates/version.dat','',$has_version['filename']);
					$data['files'][] = UpdateCenter_Helper::repackage_archive($module_name,$file,$root_folder);
				}
			}
		}
		return $data;
	}


	public function add_repository_updates($data){
		$config = UpdateCenter_Config::get();
		if(!$config->has_active_repository()){
			return $data;
		}

		$config = UpdateCenter_Config::get();
		$repo = new UpdateCenter_Repository();
		$updates = $repo->get_repository_updates();

		if(!count($updates)){
			return $data;
		}

		foreach($updates as $module_name => $obj){
			$data['update_list']['data'][$module_name] = $obj;
			if($config->is_blocked_module($module_name)){
				unset($data['update_list']['data'][$module_name]);
			}
		}

		return $data;
	}



	public function get_blocked_updates($data){
		$blocked_modules = UpdateCenter_Config::get()->get_blocked_modules();
		foreach($blocked_modules as $module){
			if(!in_array($module, $data['modules'])){
				array_push($data['modules'],$module);
			}
		}
		return $data;
	}

	public function override_ls_versions($data){
		foreach($data['modules'] as $module_name => $version){

			if(UpdateCenter_Config::is_core_module($module_name)){
				if(!UpdateCenter_Helper::is_old_ls_version($module_name,$version)){
					//Avoid errors: always send last ls version to lemonstand update center if up to date with final official release.
					$data['modules'][$module_name] = UpdateCenter_Config::get_last_ls_version($module_name);
				}
			}

			if(UpdateCenter_Config::get()->is_blocked_module($module_name)){
				unset($data['modules'][$module_name]);
			}

		}
		return $data;
	}

}
