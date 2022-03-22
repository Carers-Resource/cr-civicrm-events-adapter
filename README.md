# cr-civicrm-events-adapter

A WordPress plugin that regularly polls for events from a CiviCRM database and imports them into WordPress as a custom post type.
The plugin keeps a hash of each event so it can tell if an event has been changed.
Configration is by a dotenv file in the directory above the website directory. 
