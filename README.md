# Kaltura Account Dump API Script
This script loops through all entries, categories and chosen metadata profile (or filtered list of entries) in a specified Kaltura account. It then saves the entry and metadata fields (Entry per line) into an Excel (xml format) file.

# Configurations and running the script
To use this script, follow these steps:

1. Download the Kaltura PHP 5.3+ API Client Library and extract into the /kaltura-client directory
2. Open the accountdump.php file, and configure the following parameters:  
	* PARTNER_ID: The Kaltura Account Partner ID
	* PARTNER_NAME: The Name of the Account for logging (this doesn't really influence anything but logging...)
	* ADMIN_SECRET: The Kaltura Account ADMIN Secret (The script must run with Admin KS)
	* SERVICE_URL: The full base URL to the Kaltura server API endpoint
	* KS_EXPIRY_TIME: How long in seconds should the Kaltura session be? preferably this should be set to long, since this script may run for a while if the account has many entries.
	* ENTRY_STATUS_IN: Defines the entry statuses to retrieve  
	* ENTRY_TYPE_IN: Defines the entry types to retrieve 
	* ENTRY_FIELDS: The list of entry fields to export (excluding custom metadata, that is set in METADATA_PROFILE_ID), entryId, captions and categories will be added to this list
	* PARENT_CATEGORIES: Any IDs of Kaltura Categories you'd like to limit the export to
	* FILTER_TAGS: Any tags to filter by (tagsMultiLikeOr)
	* DEBUG_PRINTS: Set to true if you'd like the script to output logging to the console (this is different from the KalturaLogger)
	* CYCLE_SIZES: This decides how many entries will be processed in each multi-request call - set it to whatever number works best for your serve
	r, generally 300 should be a good number.
	* METADATA_PROFILE_ID: The profile id of the custom metadata profile to get its fields per entry
	* ERROR_LOG_FILE: The name of the KalturaLogger export file
	* STOP_DATE_FOR_EXPORT: Defines a stop date for the entries iteration loop. Any time string supported by strtotime can be passed. If this is set to null or -1, it will be ignored and the script will run through the entire library until it reaches the first created entry. e.g. '45 days ago' or '01/01/2017', etc. formats supported by strtotime
	* $exportFileName: This sets the name of the output excel file (without .xsl extension).
  
When done configuring the script per the above parameters, you can run the script using PHP CLI via commandline:  
```php
php accountdump.php
```

# How you can help (guidelines for contributors) 
Thank you for helping Kaltura grow! If you'd like to contribute please follow these steps:
* Use the repository issues tracker to report bugs or feature requests
* If you extend or fix anything in the code, please submit your patch as a GitHub pull-request
* Sign the [Kaltura Contributor License Agreement](https://agentcontribs.kaltura.org/)
* Read [Contributing Code to the Kaltura Platform](https://github.com/kaltura/platform-install-packages/blob/master/doc/Contributing-to-the-Kaltura-Platform.md)

# Where to get help
* Join the [Kaltura Community Forums](https://forum.kaltura.org/) to ask questions or start discussions
* Read the [Code of conduct](https://forum.kaltura.org/faq) and be patient and respectful

# Get in touch
You can learn more about Kaltura and start a free trial at: http://corp.kaltura.com    
Contact us via Twitter [@Kaltura](https://twitter.com/Kaltura) or email: community@kaltura.com  
We'd love to hear from you!

# License and Copyright Information
All code in this project is released under the [AGPLv3 license](http://www.gnu.org/licenses/agpl-3.0.html) unless a different license for a particular library is specified in the applicable library path.   

Copyright © Kaltura Inc. All rights reserved.   
Authors and contributors: See [GitHub contributors list](https://github.com/kaltura/Kaltura-Library-Export-Excel/graphs/contributors).  

### Open Source Libraries
Review the [list of Open Source 3rd party libraries](open-source-libraries.md) used in this project.
