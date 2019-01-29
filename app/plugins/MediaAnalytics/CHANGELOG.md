## Changelog

3.2.1
- Fix possible error in Live.getLastVisitDetails that causes no output

3.2.0
- Show media interactions in the visitor log and visitor profile
- Support `matomo` keyword in attributes and properties when customizing the tracking

3.1.0
- Track position witin media when a video was played, paused, resumed, or seeked.
- Track new event when a user seeks to a different position (not supported by YouTube)

3.0.19
- Piwik is now Matomo

3.0.18
- Ensure correct data is shown when an action segment is applied

3.0.17
- Improve archiving speed
- Fix media title was not kept when a video finished playing and the same video was played again
- Fix Youtube Player did not support to scan for videos on only a subset of the page, only the full page.

3.0.16
- Add possibility to set a callback method via the tracker method `MediaAnalytics::setMediaTitleFallback` to detect a custom title if no title cannot be detected automatically
- Improved detection of custom titles and resource URLs for JWplayer 5 

3.0.15
- Better support for OpenCast
- Better support for older versions of JWplayer (eg version 5)
- Fix some events for HTML5 players were not tracked under circumstances (for example resume)
- HTML5 Player. Better detection of duration, width, and height

3.0.14
- Automatically detect media titles for Opencast.

3.0.13
- Prevent possible error if a method jwplayer is defined which is not the actual jwplayer but a custom implementation

3.0.12
- Removed the need for some custom tracking code in rare cases
- Better flowplayer detection of media and flowplayer splash support

3.0.11
- Added support for Custom Reports
- Better differentiation between seek and pause for YouTube and Vimeo.

3.0.10
- HTML5 Player: Fix play event might be triggered too often, eg after a loop
- HTML5 Player: Fix pause / resume event is triggered when user is actually seeking
- Increase tracking interval over time

3.0.9
- Apply selected segment in Audience Log correctly

3.0.8
- Possibility to define custom video title to be used only for tracking when using JW Player or flowplayer.

3.0.7
- Add support for Flowplayer (only HTML5 so far)
- Add possibility to track custom resource with JW Player
- Better detection of JW Player and Flowplayer videos when they are embedded after the load event.

3.0.6
- HTML5 Player: When source changes, check if title changed as well instead of only clearing the title
- HTML5 Player: Track play event only if the player actually starts playing
- HTML5 Player: When source (video or audio) was changed, it may have missed to record updated src under circumstances

3.0.5
- Fix Unique Visitors is zero when Media Analytics is installed

3.0.4
- Full support for JW Player including Flash and M3U8
- Fixed a bug where a real time report was not updated automatically

3.0.3
- Improved support for jwplayer by detecting video title automatically

3.0.2
- Fix Overview page may require admin access

3.0.1
- Added compatibility with Roll-Up Reporting
- Better JSON object detection

3.0.0
- Initial version
