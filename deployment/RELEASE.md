# Release Checklist

This checklist is intended for CopyMyPage releases where the Joomla package and deployment-side files are handled separately.

## Release 0.0.6

Focus:

- Lighthouse optimization
- smaller project-owned CSS and JS payloads
- improved hero image delivery
- Apache caching and compression updates

## Pre-release

1. Verify the final code changes in the local Joomla development instance.
2. Build the new CopyMyPage package release.
3. Keep a backup of the currently deployed package artifacts.
4. Keep a backup of the current production `.htaccess`.

## Package deployment

1. Upload or install the new CopyMyPage package release on production.
2. Confirm that the updated frontend assets are present after deployment.
3. Confirm that the template and hero module changes are live.

## Apache deployment

1. Open `deployment/apache/.htaccess` from this repository.
2. Upload it manually to the Joomla web root on the production server.
3. Replace the existing root `.htaccess` only after a backup exists.
4. If needed, use FileZilla or another manual deployment tool for this step.

## Post-deployment checks

1. Open the production site and do a hard refresh to bypass stale browser cache.
2. Verify that the homepage renders correctly.
3. Verify that the hero slideshow still works.
4. Verify that CSS and JS assets load without 404 errors.
5. Trigger a system message once and confirm the custom message layout still works.
6. Confirm that no unexpected regressions appear in navigation, hero, or template rendering.

## Lighthouse follow-up

1. Run Lighthouse again against production after deployment.
2. Compare the new results with the previous baseline.
3. Record the new findings in the related GitHub issue or milestone notes.
4. Decide whether additional optimization work is needed for the next release.

## Notes

- `deployment/apache/.htaccess` is versioned in the repository but is not part of the normal Joomla package update flow.
- Project-owned minified assets are kept alongside their original source files for continued development.
