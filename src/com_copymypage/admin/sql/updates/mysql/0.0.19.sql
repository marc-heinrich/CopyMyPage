-- CopyMyPage-owned temporary DPCalendar ticket carts.
CREATE TABLE IF NOT EXISTS `#__copymypage_ticket_carts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `status` tinyint unsigned NOT NULL DEFAULT 0,
  `booking_id` int unsigned DEFAULT NULL,
  `payment_provider` varchar(255) DEFAULT NULL,
  `terms_accepted_at` datetime DEFAULT NULL,
  `terms_snapshot` mediumtext DEFAULT NULL,
  `revision` int unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_copymypage_ticket_cart_token` (`token_hash`),
  KEY `idx_copymypage_ticket_cart_booking` (`booking_id`),
  KEY `idx_copymypage_ticket_cart_status_expiry` (`status`,`expires_at`),
  KEY `idx_copymypage_ticket_cart_user_status` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- CopyMyPage-owned temporary DPCalendar ticket cart items.
CREATE TABLE IF NOT EXISTS `#__copymypage_ticket_cart_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` int unsigned NOT NULL,
  `event_id` int unsigned NOT NULL,
  `price_index` int unsigned NOT NULL DEFAULT 0,
  `quantity` int unsigned NOT NULL DEFAULT 0,
  `unit_price` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `currency` char(3) NOT NULL DEFAULT 'EUR',
  `price_label` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_copymypage_ticket_item_cart_event_price` (`cart_id`,`event_id`,`price_index`),
  KEY `idx_copymypage_ticket_item_event_cart` (`event_id`,`cart_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- CopyMyPage-owned customer draft belonging to one temporary ticket cart.
CREATE TABLE IF NOT EXISTS `#__copymypage_ticket_customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `account_user_id` int unsigned DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(320) NOT NULL,
  `street` varchar(500) NOT NULL,
  `house_number` varchar(100) NOT NULL,
  `postcode` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `country_code` char(2) NOT NULL,
  `country_name` varchar(100) NOT NULL,
  `region_code` varchar(16) NOT NULL DEFAULT '',
  `region_name` varchar(100) NOT NULL DEFAULT '',
  `telephone` varchar(100) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_copymypage_ticket_customer_cart` (`cart_id`),
  KEY `idx_copymypage_ticket_customer_user` (`user_id`),
  KEY `idx_copymypage_ticket_customer_account` (`account_user_id`),
  CONSTRAINT `#__fk_copymypage_ticket_customer_cart`
    FOREIGN KEY (`cart_id`) REFERENCES `#__copymypage_ticket_carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- CopyMyPage-owned immutable seating layout versions.
CREATE TABLE IF NOT EXISTS `#__copymypage_seat_layouts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(64) NOT NULL,
  `version` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `logical_width` int unsigned NOT NULL,
  `logical_height` int unsigned NOT NULL,
  `geometry_json` text NOT NULL,
  `definition_hash` char(64) NOT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_copymypage_seat_layout_alias_version` (`alias`,`version`),
  KEY `idx_copymypage_seat_layout_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- CopyMyPage-owned physical tables within an immutable seating layout.
CREATE TABLE IF NOT EXISTS `#__copymypage_layout_tables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `layout_id` int unsigned NOT NULL,
  `table_code` varchar(64) NOT NULL,
  `table_number` varchar(32) NOT NULL,
  `label` varchar(255) NOT NULL,
  `shape` varchar(16) NOT NULL,
  `x` int unsigned NOT NULL,
  `y` int unsigned NOT NULL,
  `width` int unsigned NOT NULL,
  `height` int unsigned NOT NULL,
  `rotation` smallint unsigned NOT NULL DEFAULT 0,
  `sort_order` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_copymypage_layout_table_code` (`layout_id`,`table_code`),
  UNIQUE KEY `idx_copymypage_layout_table_number` (`layout_id`,`table_number`),
  UNIQUE KEY `idx_copymypage_layout_table_order` (`layout_id`,`sort_order`),
  CONSTRAINT `#__fk_copymypage_layout_table_layout`
    FOREIGN KEY (`layout_id`) REFERENCES `#__copymypage_seat_layouts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- CopyMyPage-owned seats belonging to one physical layout table.
CREATE TABLE IF NOT EXISTS `#__copymypage_seats` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `layout_table_id` int unsigned NOT NULL,
  `seat_code` varchar(96) NOT NULL,
  `seat_number` varchar(32) NOT NULL,
  `x` int unsigned NOT NULL,
  `y` int unsigned NOT NULL,
  `sort_order` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_copymypage_seat_code` (`layout_table_id`,`seat_code`),
  UNIQUE KEY `idx_copymypage_seat_number` (`layout_table_id`,`seat_number`),
  UNIQUE KEY `idx_copymypage_seat_order` (`layout_table_id`,`sort_order`),
  CONSTRAINT `#__fk_copymypage_seat_layout_table`
    FOREIGN KEY (`layout_table_id`) REFERENCES `#__copymypage_layout_tables` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- CopyMyPage-owned seating assignment and revision for one DPCalendar event.
CREATE TABLE IF NOT EXISTS `#__copymypage_event_seating` (
  `event_id` int unsigned NOT NULL,
  `layout_id` int unsigned NOT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT 0,
  `inventory_version` int unsigned NOT NULL DEFAULT 0,
  `assignment_locked_at` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `ready_at` datetime DEFAULT NULL,
  `ready_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `idx_copymypage_event_seating_layout_status` (`layout_id`,`status`),
  CONSTRAINT `#__fk_copymypage_event_seating_layout`
    FOREIGN KEY (`layout_id`) REFERENCES `#__copymypage_seat_layouts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- CopyMyPage-owned materialised seat inventory for one DPCalendar event.
CREATE TABLE IF NOT EXISTS `#__copymypage_event_seats` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `seat_id` int unsigned NOT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT 0,
  `cart_id` int unsigned DEFAULT NULL,
  `price_index` int unsigned DEFAULT NULL,
  `assignment_order` int unsigned DEFAULT NULL,
  `ticket_id` int unsigned DEFAULT NULL,
  `block_note` varchar(500) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_copymypage_event_seat` (`event_id`,`seat_id`),
  UNIQUE KEY `idx_copymypage_event_seat_assignment` (`cart_id`,`event_id`,`assignment_order`),
  UNIQUE KEY `idx_copymypage_event_seat_ticket` (`ticket_id`),
  KEY `idx_copymypage_event_seat_status` (`event_id`,`status`,`seat_id`),
  KEY `idx_copymypage_event_seat_cart_status` (`cart_id`,`status`),
  CONSTRAINT `#__fk_copymypage_event_seat_event`
    FOREIGN KEY (`event_id`) REFERENCES `#__copymypage_event_seating` (`event_id`) ON DELETE RESTRICT,
  CONSTRAINT `#__fk_copymypage_event_seat_seat`
    FOREIGN KEY (`seat_id`) REFERENCES `#__copymypage_seats` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
