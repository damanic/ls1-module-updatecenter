ALTER TABLE updatecenter_config
ADD COLUMN enable_auto_updates TINYINT,
ADD COLUMN auto_update_interval INT(11);