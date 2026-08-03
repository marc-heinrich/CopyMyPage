-- Add the CopyMyPage-owned canonical address store.
CREATE TABLE IF NOT EXISTS `#__copymypage_addresses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `address_key` varchar(64) NOT NULL DEFAULT 'profile',
  `purpose` varchar(32) NOT NULL DEFAULT 'profile',
  `label` varchar(255) NOT NULL DEFAULT '',
  `recipient_first_name` varchar(255) NOT NULL DEFAULT '',
  `recipient_last_name` varchar(255) NOT NULL DEFAULT '',
  `company` varchar(255) NOT NULL DEFAULT '',
  `address_line_1` varchar(500) NOT NULL DEFAULT '',
  `address_line_2` varchar(500) NOT NULL DEFAULT '',
  `house_number` varchar(100) NOT NULL DEFAULT '',
  `postal_code` varchar(100) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `region` varchar(100) NOT NULL DEFAULT '',
  `region_code` varchar(16) NOT NULL DEFAULT '',
  `country_code` char(2) NOT NULL DEFAULT '',
  `country_name` varchar(100) NOT NULL DEFAULT '',
  `telephone` varchar(100) NOT NULL DEFAULT '',
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `params` text NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_copymypage_address_user_key` (`user_id`,`address_key`),
  KEY `idx_copymypage_address_user_purpose` (`user_id`,`purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- Import only contacts previously marked as CopyMyPage-managed profile addresses.
-- INSERT IGNORE and the stable per-user address key keep repeated updates idempotent.
INSERT IGNORE INTO `#__copymypage_addresses`
  (
    `user_id`, `address_key`, `purpose`, `address_line_1`, `postal_code`,
    `city`, `region`, `country_name`, `is_default`, `state`, `params`,
    `created`, `modified`
  )
SELECT
  `user_id`, 'profile', 'profile', `address`, `postcode`,
  `suburb`, `state`, `country`, 1, 1, '{}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM `#__contact_details`
WHERE `user_id` > 0
  AND (
    `alias` = CONCAT('copymypage-profile-address-', `user_id`)
    OR `params` LIKE '%"copymypage_profile_address":1%'
  );
