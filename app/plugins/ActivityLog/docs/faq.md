## FAQ

__What does Activity Log do?__

The Activity Log plugins keeps a record of all important activities performed by your Matomo users on your Matomo.
You can view all activities that happened in the past in a chronological order to see who did what and when.

The Activity Log allows Super Users to quickly review the actions performed in Matomo by members of your organization or clients. 
It also lets every one of these user also review details of their own actions. 


__Why is it important to keep an eye on these activities?__

There are many reasons why it is important, for example:

* Accountability: It helps you to identify which users were associated with a certain activity or event.
* Intrusion Detection: It helps you to monitor data for any potential security breach or misuse of information.
* Problem Detection: It helps you to identify problems, why something happened and when.

__Who develops & maintains the Activity Log plugin?__

The plugin is developed and maintained by [InnoCraft](https://www.innocraft.com), the company of the makers of Matomo. 
At InnoCraft, talented and passionate developers build and maintain the free and open source project Matomo. 
This ensures that the plugin is well integrated, kept up to date and automatically tested whenever a change is made. 
By purchasing this plugin you also help the developers to being able to maintain the free and open source project Matomo itself.

__How do I access the activity log?__

First you need to log in to your Matomo. 

If you are a [Super User](https://matomo.org/faq/general/faq_35/), go to "Administration" and click in the "Diagnostic" 
section on "Activity Log". 

As a user with view or admin access you can see your activity log entries by clicking on "Personal" in the top right corner followed
by clicking on "Activity Log" in the left menu.

__Who has access to the activity log?__

[Super Users](https://matomo.org/faq/general/faq_35/) are able to see all activities and can also filter activities by user.
 
All other users can view their own activities.

__Can the activity log data be exported?__ 

You can use the [Matomo HTTP API](https://developer.matomo.org/api-reference/reporting-api#ActivityLog) to query activities.
 
The plugin currently adds the following API methods to your Matomo:

* `ActivityLog.getEntries` Returns logged activity entries.
* `ActivityLog.getEntryCount` Returns the number of available activity entries.

An export feature will be also available in the UI soon.

__How long will the activity data be stored?__

Activities are stored forever, there are no limits. If you are interested in a feature to setup an automatic purge
of activities after a certain time, [let us know](https://matomo.org/support).

__How do I enable Gravatar images in the activity log?__

[Gravar](https://en.gravatar.com/) means Globally Recognized Avatar. When enabled, it will try to find a matching
avatar image for your users so you can easily see which user has performed which activity. An avatar image may be 
shown next to an activity in the activity log. This feature is not enabled by default as our plugins 
do not send any of your data or metadata to external web services for [privacy](https://matomo.org/privacy) compliance.  

To enable Gravatar images, log in to Matomo as a [Super User](https://matomo.org/faq/general/faq_35/) and go to 
"Administration => Plugin Settings", where you can enable the Gravatar setting.

__As a developer, how do I log activities done within my custom plugin?__

To log custom activities happening in your custom plugin, you can define Activity classes (extending `Piwik\Plugins\ActivityLog\Activity\Activity`). 
You need to place these classes in a directory named `Activity` within any plugin. The Audit log will then include all
 such activities recorded by your plugin. 

__How can I export the Activity Log UI to embed it somewhere else?__
 
First you need to log in to your Matomo. Then click on "Personal" in the top right corner and click on "Widgets"
in the left menu. There you can find the widget "Activity Log" in the "Diagnostic" section. Below the widget the URL
to export it is shown. To learn more about this, read the [Embed Matomo Widget](https://matomo.org/docs/embed-piwik-report/) user guide.

__Which events / activities are being tracked?__

The audit log reports all these activities:

* Annotation added
* Annotation changed
* Annotation deleted
* Component updated (Matomo / Plugin)
* Custom Alert added
* Custom Alert changed
* Custom Alert deleted
* Custom Dimension configured
* Custom Dimension changed
* Geo location provider changed
* Goal added
* Goal changed
* Goal deleted
* Measurable created
* Measurable changed
* Measurable removed
* Plugin installed
* Plugin uninstalled
* Plugin activated
* Plugin deactivated
* Privacy: Enable DNT support
* Privacy: Disable DNT support
* Privacy: Set IP Anonymise settings 
* Privacy: Set delete logs settings
* Privacy: Set delete reports settings
* Privacy: Set scheduled report deletion setting
* Scheduled report created
* Scheduled report changed
* Scheduled report deleted
* Scheduled report sent
* Segment created
* Segment updated
* Segment deleted
* Site access changed
* Site settings updated
* Super user access changed
* System settings updated
* User created
* User removed
* User changed
* User logged in
* User failed to log in
* User logged out
* User settings updated
* User sets preference

Other plugins' activity log events:

* A/B testing
    - Experiment created
    - Experiment settings updated
    - Experiment status changed (Started, Finished, Archived)
    - Experiment deleted
* Custom Reports
    - Custom Report created
    - Custom Report updated
    - Custom Report deleted
* Form Analytics
    - Form created
    - Form updated
    - Form deleted
    - Form archived
* Heatmaps
    - Heatmap created
    - Heatmap updated
    - Heatmap deleted
    - Heatmap stopped
* Session Recording
    - Session Recording created
    - Session Recording updated
    - Session Recording deleted
    - Session Recording stopped
* Referrers Manager
    - Search engine added
    - Search engine removed
    - Social network added
    - Social network removed
    
__Do I get access to the raw data that was tracked?__

Yes, if you host Matomo yourself you get access to all data that is stored in your MySQL database. 
The data is stored in a table called `matomo_activity_log`. The data is also made easily available via the 
[Activity Log HTTP Reporting API](/api-reference/reporting-api#ActivityLog).
