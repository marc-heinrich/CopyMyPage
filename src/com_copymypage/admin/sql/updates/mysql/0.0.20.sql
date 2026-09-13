-- Event-specific business allocation; status=blocked remains the online lock.
ALTER TABLE `#__copymypage_event_seats`
  ADD COLUMN `allocation_type` tinyint unsigned NOT NULL DEFAULT 0 AFTER `status`;

-- Localised customer mail templates for managed DPCalendar bookings.
-- Language-specific rows win over DPCalendar's empty-language defaults.
-- INSERT IGNORE preserves administrator customisations.
INSERT IGNORE INTO `#__mail_templates`
  (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`)
VALUES
  ('com_dpcalendar.booking.user.new', 'com_copymypage', 'de-DE', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.new', 'com_copymypage', 'en-GB', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.new', 'com_copymypage', 'es-ES', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.new', 'com_copymypage', 'fr-FR', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.new', 'com_copymypage', 'it-IT', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.pay', 'com_copymypage', 'de-DE', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.pay', 'com_copymypage', 'en-GB', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.pay', 'com_copymypage', 'es-ES', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.pay', 'com_copymypage', 'fr-FR', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}'),
  ('com_dpcalendar.booking.user.pay', 'com_copymypage', 'it-IT', 'COM_COPYMYPAGE_BOOKING_MAIL_SUBJECT', 'COM_COPYMYPAGE_BOOKING_MAIL_BODY', 'COM_COPYMYPAGE_BOOKING_MAIL_HTMLBODY', '', '{"tags":["sitename","user","booking","events"]}');
