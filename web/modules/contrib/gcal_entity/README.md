# Google Calendar Entity (Gcal Entity)
GCal Entity provides an easy way to display Google calendar events in an agenda format. Events are displayed in a simple agenda list format, with date and event title.
## Installation
There are several methods to install: 
- Run command: <code> composer require drupal/gcal_entity</code> 
- Download the latest version, extract and manually move to your “/modules” directory. 
- Run command: <code>drush en gcal_entity</code> (or enable in the “Extend” interface)
### Configuration (Global Settings)
These settings apply to all calendars.
1. In Drupal, go to Admin > Content authoring > Google Calendar Entity Global Settings.
2. Paste in your Google API key.
3. Set cache time limit. A lower number refreshes the information faster but may impact site performance. (Caching is set up for 5 minutes (300 seconds) by default. 0 is no cache--not recommended for anything but very light traffic sites.) 
4. Change other settings as desired.
5. Click Save Configuration.
## Usage
### Creating a New Calendar
1. Structure/Content > Gcal Entity. Page shows list of created calendars.</li>
2. At the bottom left corner, click Add GCal entity.</li>
3. You will need your calendar's id, which is always in an email format.</li>
### Inserting Calendar
On desired content type(s), insert as entity reference field.
### Extending the Module
The basic twig templates can be copied to your theme. Each template provides detailed information for what calendar variables are available.