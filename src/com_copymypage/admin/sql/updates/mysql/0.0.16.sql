-- Add the CopyMyPage-owned sender-copy mail template introduced in 0.0.16.
-- INSERT IGNORE deliberately preserves templates already customised in Joomla.
INSERT IGNORE INTO `#__mail_templates`
  (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`)
VALUES
  (
    'com_copymypage.contact.copy',
    'com_copymypage',
    '',
    'COM_COPYMYPAGE_CONTACT_COPY_MAIL_SUBJECT',
    'COM_COPYMYPAGE_CONTACT_COPY_MAIL_BODY',
    'COM_COPYMYPAGE_CONTACT_COPY_MAIL_HTMLBODY',
    '',
    '{"tags":["sitename","name","email","subject","body","url","customfields","contactname"]}'
  );
