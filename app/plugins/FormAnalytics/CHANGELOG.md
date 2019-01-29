## Changelog

3.1.0
- Improve how form and form field interactions are shown in the visitor log
- Improved API response for form interactions in the Live API methods
- Support `matomo` keyword in attributes and properties when customizing the tracking

3.0.15
- Prevent possible fatal error when opening manage screen for all websites
- Keep a visitors session (visit) alive every couple of minutes
- Better error message when renaming a form but the name of the form is already in use

3.0.14
- Renamed Piwik to Matomo

3.0.13
- Fix possible bug in visitor profile where a wrong value may be assigned.

3.0.12
- Fix possible bug in visitor log when there are no visitors

3.0.11
- Improve memory usage and performance of performance of visitor log and visitor profile integration

3.0.10
- Improve performance of visitor log and visitor profile
- Format sparkline metrics
- Fix a bug when viewing visitor log as user with view access only

3.0.9
- Show form interactions in visitor log and visitor profile

3.0.8
- Fix max 100 forms per page where loaded when managing forms for a site
- Added support for Custom Reports plugin
- Send several form views along a page view instead of only one to reduce server load

3.0.7
- Fix a form conversion may under circumstances not be tracked if a form is interacted with without any break or when it only includes a submit button.

3.0.6
- Make sure to count a new form start after a form submission
- Prevent some edge case racing conditions when a form submit and conversion is tracked directly after another

3.0.5
- Make sure form rules work fine when using HTML entities

3.0.4
- Add support for TinyMCE
- Add support for select2

3.0.3
- Enrich system summary widget with the number of forms
- Fix all columns view in Live widget did not show label

3.0.2
- Fix a tracking bug on IE9 and older

3.0.1
- Show Manage Forms in reporting menu

3.0.0 
- Initial version
