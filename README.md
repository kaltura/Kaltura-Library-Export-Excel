# Kaltura data export


# Configuration
Before running the script, follow these steps:

1. Download the [Kaltura PHP 5.3+ API Client archive](https://developer.kaltura.com/api-docs/Client_Libraries) and extract onto the `BASE_DIR/kaltura-client` directory (or edit `accountdump.php` to point to a different path)
2. Edit `accountdump.php` and set the following parameters:  
	* `PARTNER_ID`: the Kaltura partner ID
	* `PARTNER_NAME`: the account name (only used logging and output filename)
	* `ADMIN_SECRET`: the partner's ADMIN secret 
	* `SERVICE_URL`: the Kaltura API endpoint (https://www.kaltura.com when using SaaS)
	* `KS_EXPIRY_TIME`: Session duration; since the execution time will vary based on the number of records, be sure to set the duration accordingly.
	* `ENTRY_STATUS_IN`: defines the entry statuses to retrieve  
	* `ENTRY_TYPE_IN`: defines the entry types to retrieve 
	* `ENTRY_FIELDS`: entry object members to export (excluding custom metadata, that is set in `METADATA_PROFILE_ID`), `entryId`, captions and categories will be added to the above
	* `PARENT_CATEGORIES`: optional; IDs of Kaltura Categories you'd like to limit the export to
	* `FILTER_TAGS`: tags to filter by (`tagsMultiLikeOr`)
	* `CYCLE_SIZES`: determines how many entries will be processed in each multi-request call
	* `METADATA_PROFILE_ID`: the profile id of the custom metadata profile to get its fields per entry
	* `ONLY_CAPTIONED_ENTRIES`: when set to `true` only entries with caption assets be included in the output
	* `GET_CAPTION_URLS`: when set to `true`, caption download URLs will be included
	* `ERROR_LOG_FILE`: the name of the `KalturaLogger` export file
	* `STOP_DATE_FOR_EXPORT`: defines a stop time for the iteration loop. Any input supported by `strtotime` can be passed  e.g. '45 days ago' or '01/01/2017', etc. . If set to null or -1, it will be ignored and the script will iterate over all entries matching the other criteria.
	* `DEBUG_PRINTS`: set to true if you'd like the script to output logging to the console (this is different from the `KalturaLogger`)
	* `$exportFileName`: sets the name of the output XLS file (do not include the file extension).
  
After setting the values for the above parameters, run the script using PHP CLI:  
```
$ php accountdump.php
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
