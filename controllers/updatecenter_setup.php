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
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		protected function index_onSave()
		{
			$record = UpdateCenter_Config::get();
			$modules = Core_ModuleManager::listModules();
			$blocked_modules = array();

			foreach ($modules as $module) {
				$module_id   = mb_strtolower( $module->getId() );
				if(post_array_item('UpdateCenter_Config','block_module_'.$module_id, false)){
					$blocked_modules[] = $module_id;
				}

			}
			$record->blocked_modules = implode(',',$blocked_modules);
			$record->save();
			$this->edit_onSave( $record->id );
		}
		
		protected function index_onCancel()
		{
			$record = UpdateCenter_Config::get();
			$this->edit_onCancel($record->id);
		}
	}

?>