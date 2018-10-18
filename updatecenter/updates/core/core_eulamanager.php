<?

	class Core_EulaManager
	{
		public static function pull()
		{
			$request = Net_Request::create(self::get_endpoint_url());

			$request->set_timeout(5);
			$request->disable_redirects();
			$response = $request->send();
			$last_accepted_version = Db_ModuleParameters::get('core', 'laeav');

			if ($response->status_code != 200){
				return false; //if server down assume no new agreements issued.
			}


			$pos = strpos($response->data, '|');
			if ($pos === false)
				throw new Phpr_ApplicationException('Invalid End User License Agreement document received.');

			$version = substr($response->data, 0, $pos);
			$content = substr($response->data, $pos+1);

			if ($last_accepted_version < $version)
			{
				Db_ModuleParameters::set('core', 'lei', array('v'=>$version, 'c'=>$content));
				return true;
			}
			
			return false;
		}
		
		protected static function get_endpoint_url()
		{
			$eula_type = Phpr::$config->get('LS_EULA_TYPE', 'std-eula');
			return Phpr::$config->get('LS_EULA_GATEWAY', 'https://v1.lemonstand.com/lemonstand_eula/').$eula_type.'/';
		}
		
		public static function get_saved_eula_data()
		{
			return Db_ModuleParameters::get('core', 'lei', array());
		}
		
		public static function commit($login = null, $first_name = null, $last_name = null)
		{
			$data = Core_EulaManager::get_saved_eula_data();
			if (!$data)
				return;

			self::push($login, $first_name, $last_name, $data);
			Core_EulaInfo::update_info($data['c']);
			Db_ModuleParameters::set('core', 'laeav', $data['v']);

			try 
			{
				$user = Phpr::$security->getUser();

				$administrators = Users_User::listAdministrators();
				$recipient_list = array();
				
				if (!$user)
					$recipient_list = $administrators;
				else
				{
					foreach ($administrators as $administrator)
					{
						if ($administrator->id != $user->id)
							$recipient_list[] = $administrator;
					}
				}
				
				$viewData = array(
					'user_name'=>($user ? $user->name : 'Unknown user'),
					'agreement_text'=>$data['c']
				);
				Core_Email::sendToList('core', 'accepted_agreement', $viewData, 'Updated LemonStand End User License Agreement has been accepted', $administrators);
			} catch (exception $ex) {}
		}
		
		protected static function push($login, $first_name, $last_name, $agreement_data)
		{
			$hash = Db_ModuleParameters::get('core', 'hash');
			if (!$hash)
				throw new Phpr_ApplicationException('Invalid license information');

			$framework = Phpr_SecurityFramework::create();
			$hash = $framework->decrypt(base64_decode($hash));
			$user = Phpr::$security->getUser();

			try
			{
				$request = Net_Request::create(self::get_endpoint_url());
				
				$request->set_timeout(30);
				$request->disable_redirects();

				$request->set_post(array(
					'license_hash'=>$hash, 
					'version'=>$agreement_data['v'],
					'datetime'=>Phpr_DateTime::gmtNow()->format(Phpr_DateTime::universalDateTimeFormat),
					'login_name'=>($user ? $user->login : $login),
					'user_first_name'=>($user ? $user->firstName : $first_name),
					'user_last_name'=>($user ? $user->lastName : $last_name)
				));

				$response = $request->send();
				if ($response->status_code != 200)
					throw new Phpr_ApplicationException('Invalid response code received: '.$response->status_code);
			}
			catch (exception $ex) {
				throw new Phpr_ApplicationException('Error sending request to the server. '.$ex->getMessage());
			}
		}
	}

?>