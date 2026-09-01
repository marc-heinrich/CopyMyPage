-- Persist the legally relevant Step-4 acceptance next to the converted ticket cart.
ALTER TABLE `#__copymypage_ticket_carts`
  ADD COLUMN `payment_provider` varchar(255) DEFAULT NULL AFTER `booking_id`,
  ADD COLUMN `terms_accepted_at` datetime DEFAULT NULL AFTER `payment_provider`,
  ADD COLUMN `terms_snapshot` mediumtext DEFAULT NULL AFTER `terms_accepted_at`,
  ADD KEY `idx_copymypage_ticket_cart_booking` (`booking_id`);
