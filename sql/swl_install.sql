CREATE TABLE IF NOT EXISTS civicrm_swl_user_details (
  user_id int(10) unsigned NOT NULL COMMENT 'SWL internal user id.',  
  email varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Swl user email address',
  PRIMARY KEY (`user_id`,`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
