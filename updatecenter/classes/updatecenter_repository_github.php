<?php
class UpdateCenter_Repository_github extends UpdateCenter_Repository_Driver implements UpdateCenter_Repository_Interface {

	protected $releases_uri = 'https://api.github.com/repos/{owner}/{repo}/releases/latest';
	protected $source_uri = 'https://api.github.com/repos/{owner}/{repo}/zipball/{branch}';
	protected $markdown_uri  = 'https://api.github.com/markdown';
	protected $cache;

	public function get_latest_version($module_name){
		if(isset($this->cache[$module_name]['latest_version'] )){
		return $this->cache[$module_name]['latest_version'];
		}

		$repo_details = isset($this->module_info[$module_name]) ? $this->module_info[$module_name] : false;
		if(!$repo_details){
			throw new Phpr_ApplicationException('No github repository information found for '.$module_name);
		}

		if(!isset($repo_details['repo']) || !isset($repo_details['owner'])){
			throw new Phpr_ApplicationException('updatecenter config is missing repo and owner parameters for module:'.$module_name);
		}

		$auth = isset($repo_details['auth']) ? $repo_details['auth'] : array();
		$repo_data = $this->request_server_data($this->get_latest_release_uri($repo_details['repo'],$repo_details['owner']), array(), $auth);

		if(isset($repo_data['message'])){
			throw new Phpr_ApplicationException('Message from GitHub: '.$repo_data['message']);
		}

		if(!isset($repo_data['tag_name'])){
			throw new Phpr_ApplicationException('There are no releases for module `'.$module_name.'` on GitHub repo '.$repo_details['owner'].':'.$repo_details['repo'].'. To resolve this issue remove this repo from your config file or add a release version to the repository');
		}

		$this->cache[$module_name]['latest_version'] = $repo_data;
		return $repo_data;
	}

	public function get_latest_version_number($module_name){
		try {
			$repo_data = $this->get_latest_version( $module_name );
			return $repo_data['tag_name'];
		} catch (Exception $e){
			traceLog($e->getMessage());
			return false;
		}
	}

	public function get_latest_version_description($module_name){
		try {
			$repo_data = $this->get_latest_version($module_name);
			return $repo_data['name'].' | '. strtok($repo_data['body'], "\n");
		} catch (Exception $e){
			return '';
		}
	}

	public function get_latest_version_zip_url($module_name){
		$repo_details = $this->module_info[$module_name];

		//allow override to branch zipball for bleeding edge updates
		if(isset($repo_details['edge_updates']) && !empty($repo_details['edge_updates'])){
			$repo_edge = $repo_details['edge_updates'];
			$edge_owner = empty($repo_edge['owner'])? $repo_details['owner'] : $repo_edge['owner'];
			$edge_repo =  empty($repo_edge['repo'])? $repo_details['repo'] : $repo_edge['repo'];
			$edge_branch =  empty($repo_edge['branch'])? 'master': $repo_edge['branch'];
			return $this->get_branch_zipball_url($edge_repo,$edge_owner,$edge_branch);
		}
		else if(isset($repo_details['git_use_branch']) && !empty($repo_details['git_use_branch'])){
			//git_use_branch deprecated
			return $this->get_branch_zipball_url($repo_details['repo'],$repo_details['owner'],$repo_details['git_use_branch']);
		}

		$asset = $this->get_latest_version_zip_info($module_name);
		return $asset['browser_download_url'];
	}

	public function get_latest_version_zip_size($module_name){
		$repo_details = $this->module_info[$module_name];

		if(isset($repo_details['git_use_branch']) && !empty($repo_details['git_use_branch'])) { //downloading latest source, size unknown
			return 0;
		}

		$asset = $this->get_latest_version_zip_info($module_name);
		return $asset['size'];

	}


	public function get_latest_version_zip_info($module_name){
		$repo_data = $this->get_latest_version($module_name);
		if(isset($repo_data['assets'])) {
			foreach ( $repo_data['assets'] as $key => $asset ) {
				if ( $asset['content_type'] == ('application/zip' || 'application/x-zip-compressed') ) {
					return $asset;
				}
			}
		}

		//asset zip not found try the zip_ball
		$asset = array();
		$asset['content_type'] = 'application/zip';
		$asset['browser_download_url']  = $repo_data['zipball_url'];
		$asset['size']  = 0; //unreported by api
		return $asset;

	}


	public function get_auth_headers($auth=null){
		$auth_token_set = false;
		$headers = array();
		if(is_array($auth)){
			if(isset($auth['token']) && !empty($auth['token'])){
				$auth_token_set = true;
				$headers[] = "Authorization: token ".$auth['token'];
			}
		}

		if(!$auth_token_set){
			$config = UpdateCenter_Config::get();
			$token = $config->github_auth_key;
			if(!empty($token)){
				$auth_token_set = true;
				$headers[] = "Authorization: token ".$token;
			}
		}
		return $headers;
	}



	protected function get_latest_release_uri($repo,$owner){
		$uri = str_replace('{owner}',$owner, $this->releases_uri);
		$uri = str_replace('{repo}',$repo, $uri);
		return $uri;
	}

	protected function get_branch_zipball_url($repo,$owner,$branch){
		$uri = str_replace('{owner}',$owner, $this->source_uri);
		$uri = str_replace('{repo}',$repo, $uri);
		$uri = str_replace('{branch}',$branch, $uri);
		return $uri;
	}




	protected function request_server_data($url, $fields = array(), $auth = array())
	{
		$result = null;
		$headers = array();
		try
		{
			$poststring = array();

			if(is_array($fields)) {
				foreach ( $fields as $key => $val ) {
					$poststring[] = urlencode( $key ) . "=" . urlencode( $val );
				}
				$poststring = implode('&', $poststring);
			}



			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_USERAGENT, 'PHP/Lemonstand');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);


			if(is_array($fields) && count($fields)){
				$headers[] = 'Content-Type: application/json';
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
			}


			$headers = array_merge($headers,$this->get_auth_headers($auth));

			if(count($headers)){
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}

			$result = curl_exec($ch);

			if (curl_errno($ch) == 60){
				curl_setopt($ch, CURLOPT_CAINFO, PATH_APP.'/modules/updatecenter/resources/ssl/cacert.pem');
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				$result = curl_exec($ch);
			}

			if (curl_errno($ch))
				throw new Phpr_ApplicationException( "Error connecting the update server." );
			else
				curl_close($ch);

		} catch (Exception $ex) {}

		if (!$result || !strlen($result))
			throw new Exception("Error connecting to the GitHub API.");

		$result_data = json_decode($result,true);



		return $result_data ? $result_data : $result;
	}
}