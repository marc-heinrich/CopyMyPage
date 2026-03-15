# Apache Deployment Files

This folder stores Apache-level deployment artifacts that belong to the project but are not part of the Joomla extension package.

## Current file

- `.htaccess`

## Intended usage

- Keep the file versioned in this repository.
- Upload it manually to the Joomla web root on the target server.
- Do not include it in the normal CopyMyPage package update flow.

## Typical target location

- Joomla installation root
- Same directory as `index.php`

## Release workflow

1. Build and deploy the normal CopyMyPage package.
2. Upload `deployment/apache/.htaccess` to the Joomla root, for example via FileZilla.
3. Keep a backup of the server's previous `.htaccess`.
4. After larger Joomla core updates, compare the current Joomla `htaccess.txt` with this project file and merge any relevant core changes.
