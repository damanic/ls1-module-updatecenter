<?

	class UpdateCenter_Setup extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);
		public $implement = 'Db_FormBehavior';

		public $form_edit_title = 'Configure Additional Update Options';
		public $form_model_class = 'UpdateCenter_Config';
		
		public $form_redirect = null;
		public $form_edit_save_flash = 'Update Config has been saved.';

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			
			$this->form_redirect = url('updatecenter/setup/');
		}

		public function index()
		{
			try
			{
				$record = UpdateCenter_Config::get();
				if (!$record)
					throw new Phpr_ApplicationException('Update Center configuration is not found.');
				
				$this->edit($record->id);
				$this->app_page_title = $this->form_edit_title;
				$this->viewData['updater'] = new UpdateCenter_CoreUpdate();
				$this->viewData['available_updates']  =  false;

				$config = UpdateCenter_Config::get();
				if($config->has_active_repository() || post('config_switch', false)){
					$this->viewData['available_updates']  =  $config->get_available_updates(post('config_switch', null));
				}
				if(post('config_switch', false)){
					$this->renderPartial('allow_repo_module_checkboxes');
				}
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		protected function index_onSave()
		{
			$record = UpdateCenter_Config::get();

			//GATHER BLOCKED MODULES
			$modules = Core_ModuleManager::listModules();
			$blocked_modules = array();
			foreach ($modules as $module) {
				$module_id   = mb_strtolower( $module->getId() );
				if(post_array_item('UpdateCenter_Config','block_module_'.$module_id, false)){
					$blocked_modules[] = $module_id;
				}

			}
			$record->blocked_modules = implode(',',$blocked_modules);

			//GATHER APPROVED REPO UPDATES
			$repo_config_set = post_array_item('UpdateCenter_Config','repository_config', false);
			if($repo_config_set){
				$selected_updates = array();
				$available_updates = $record->get_available_updates($repo_config_set);
				foreach ($available_updates as $source => $update){
					foreach($update as $module_id => $update_info){
						$selected_updates[$source][$module_id] = post_array_item('UpdateCenter_Config','allow_repo_update_'.$source.'_'.$module_id, 0);
					}
				}
				$record->repo_allowed_updates = serialize($selected_updates);
			} else {
				$record->repo_allowed_updates = null;
			}
			$record->save();
			$this->edit_onSave( $record->id );
		}

		protected function index_onPatch(){
				$updater = new UpdateCenter_CoreUpdate();
				$updater->make_compatible();
				Phpr::$response->redirect(url('updatecenter/setup'));
		}
	}

?>