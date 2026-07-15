-- Remove only the mail template owned by CopyMyPage.
DELETE FROM `#__mail_templates`
WHERE `template_id` = 'com_copymypage.contact.copy'
  AND `extension` = 'com_copymypage';
