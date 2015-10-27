CREATE TABLE `updatecenter_config` (
  `id` int(11) NOT NULL auto_increment,
  `blocked_modules` text default NULL,
  `repository_config` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;