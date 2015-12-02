<?
	$repository_info = array(
		'name'=>'Core Module Updates | github:damanic',
		'description'=>'Bugfixes and new event additions to core modules no longer updated by lemonstand (cms,core,etc). Plus updates to the updatecenter module',

		'repositories' => array(
			array(
				'source' =>	'github',
				'modules' => array(
					'core' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-core',
						'default_allow_update' => true,
						'view_info_url' => 'https://github.com/damanic/ls1-module-core/blob/master/readme.md',
						'view_releases_url' => 'https://github.com/damanic/ls1-module-core/releases',
					),
					'cms' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-cms',
						'default_allow_update' => false,
						'view_info_url' => 'https://github.com/damanic/ls1-module-cms/blob/master/readme.md',
						'view_releases_url' => 'https://github.com/damanic/ls1-module-cms/releases',
					),
					'updatecenter' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-updatecenter',
						'default_allow_update' => true,
						'view_info_url' => 'https://github.com/damanic/ls1-module-updatecenter/blob/master/readme.md',
						'view_releases_url' => 'https://github.com/damanic/ls1-module-updatecenter/releases',
					),
					'backend' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-backend',
						'default_allow_update' => false,
						'view_info_url' => 'https://github.com/damanic/ls1-module-backend/blob/master/readme.md',
						'view_releases_url' => 'https://github.com/damanic/ls1-module-backend/releases',
					),
					'shop' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-backend',
						'default_allow_update' => false,
						'view_info_url' => 'https://github.com/damanic/ls1-module-shop/blob/master/readme.md',
						'view_releases_url' => 'https://github.com/damanic/ls1-module-shop/releases',
					),
				)
			)
		),

	);

?>