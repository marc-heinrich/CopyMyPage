-- Remove only seating data owned by CopyMyPage, children before parents.
DROP TABLE IF EXISTS `#__copymypage_event_seats`;
DROP TABLE IF EXISTS `#__copymypage_event_seating`;
DROP TABLE IF EXISTS `#__copymypage_seats`;
DROP TABLE IF EXISTS `#__copymypage_layout_tables`;
DROP TABLE IF EXISTS `#__copymypage_seat_layouts`;

-- Remove only temporary ticket carts owned by CopyMyPage.
DROP TABLE IF EXISTS `#__copymypage_ticket_customers`;
DROP TABLE IF EXISTS `#__copymypage_ticket_cart_items`;
DROP TABLE IF EXISTS `#__copymypage_ticket_carts`;

-- Remove only address data owned by CopyMyPage.
DROP TABLE IF EXISTS `#__copymypage_addresses`;

-- Remove only the mail template owned by CopyMyPage.
DELETE FROM `#__mail_templates`
WHERE `template_id` = 'com_copymypage.contact.copy'
  AND `extension` = 'com_copymypage';
