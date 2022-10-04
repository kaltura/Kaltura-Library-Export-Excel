<?php
set_time_limit(0);
ini_set( 'memory_limit' , '1024M' );
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
date_default_timezone_set('America/New_York'); //make sure to set the expected timezone

// download the PHP5.3+ client from https://developer.kaltura.com/api-docs/Client_Libraries
require_once(dirname (__FILE__) . '/kaltura-client/KalturaClient.php');
require_once(dirname (__FILE__) . '/php-excel/php-excel.class.php');

class KalturaContentAnalytics implements IKalturaLogger 
{
	const PARTNER_ID = 000000;  //The Kaltura Account Partner ID
	const PARTNER_NAME = 'PARTNER NAME'; //The Name of the Account for logging and exported filename
	const ADMIN_SECRET = 'ADMIN SECRET'; // The Kaltura Account ADMIN Secret (The script must run with Admin KS)
	// The Kaltura server API endpoint
	const SERVICE_URL = 'https://www.kaltura.com'; 
	// Kaltura session length. Please note the script may run for a while so it mustn't be too short.
	const KS_EXPIRY_TIME = 86000; 
	// defines the entry statuses to retrieve. Add KalturaEntryStatus::DELETED to include deleted entries. 
	const ENTRY_STATUS_IN = array(KalturaEntryStatus::PRECONVERT, KalturaEntryStatus::READY, KalturaEntryStatus::PENDING, KalturaEntryStatus::MODERATE, KalturaEntryStatus::BLOCKED, KalturaEntryStatus::NO_CONTENT); 
	const ENTRY_TYPE_IN = array(KalturaMediaType::VIDEO, KalturaMediaType::AUDIO); //defines the entry types to retrieve 
	 // the entry object members to export (excluding custom metadata, that is set in METADATA_PROFILE_ID), entry ID, captions and categories will be added to the below
	const ENTRY_FIELDS = array('name', 'userId', 'msDuration', 'createdAt', 'updatedAt', 'lastPlayedAt', 'status', 'views', 'plays', 'tags', 'adminTags');
	// The IDs of the Kaltura Categories you'd like to export, set to `null` to export all.
	const PARENT_CATEGORIES = null;
	 // Tags to filter by (tagsMultiLikeOr)
	const FILTER_TAGS = null; 
	 // Determines how many entries will be processed in each multi-request call - set it to whatever number works best for your server.
	const CYCLE_SIZES = 500;
	const METADATA_PROFILE_ID = null; // The profile id of the custom metadata profile to get its fields per entry
	const ONLY_CAPTIONED_ENTRIES = false; // Should only entries that have caption assets be included in the output?
	const GET_CAPTION_URLS = false; // Should the excel include URLs to download caption assets?
	const ERROR_LOG_FILE = 'kaltura_logger.txt'; //The name of the KalturaLogger export file
	//Set to true if you'd like the script to output logging to the console (this is different from the KalturaLogger)
	const DEBUG_PRINTS = true;

	// Defines a stop time for the iteration loop. Any input supported by `strtotime` can be passed  e.g. '45 days ago' or '01/01/2017', etc. . If set to null or -1, it will be ignored and the script will iterate over all entries matching the other criteria.
	const STOP_DATE_FOR_EXPORT = null;

	private $exportFileName = 'account-entries-dump'; //This sets the name of the output excel file (without .xsl extension)
	
	private $stopDateForCreatedAtFilter = null;
	private $captionLanguages = array();
	private $ks = null;
	private $client = null;
	private $kConfig = null;

	public function log($message)
	{
		$errline = date('Y-m-d H:i:s') . ' ' .  $message . "\n";
		file_put_contents(KalturaContentAnalytics::ERROR_LOG_FILE, $errline, FILE_APPEND);
	}

	public function run()
	{
		//Reset the log file:
		$errline = "Here you'll find the log form the Kaltura Client library, in case issues occur you can use this file to investigate and report errors.";
		file_put_contents(KalturaContentAnalytics::ERROR_LOG_FILE, $errline);
		//This sets how far back we'd like to export entries (list is ordered in descending order from today backward)
		if (KalturaContentAnalytics::STOP_DATE_FOR_EXPORT != null && KalturaContentAnalytics::STOP_DATE_FOR_EXPORT != -1) {
			$this->stopDateForCreatedAtFilter = strtotime(KalturaContentAnalytics::STOP_DATE_FOR_EXPORT);
			echo 'Exporting Kaltura entries since: '.KalturaContentAnalytics::STOP_DATE_FOR_EXPORT.' (timestamp: '.$this->stopDateForCreatedAtFilter.')'.PHP_EOL;
		}
		
		//This sets the name of the output excel file (without .XLS extension)
		$this->exportFileName = $this->convert_to_filename(KalturaContentAnalytics::PARTNER_NAME).'-kaltura-export'; 

		$kConfig = new KalturaConfiguration(KalturaContentAnalytics::PARTNER_ID);
		$kConfig->serviceUrl = KalturaContentAnalytics::SERVICE_URL;
		$kConfig->setLogger($this);	
		$this->client = new KalturaClient($kConfig);

		$this->ks = $this->client->session->start(KalturaContentAnalytics::ADMIN_SECRET, 'video-minutes-calc', KalturaSessionType::ADMIN, KalturaContentAnalytics::PARTNER_ID, KalturaContentAnalytics::KS_EXPIRY_TIME, 'disableentitlement,list:*');
		$this->client->setKs($this->ks);

		echo 'Generating report for partner: ' . KalturaContentAnalytics::PARTNER_NAME . ', ID: ' . KalturaContentAnalytics::PARTNER_ID . ' - ' . PHP_EOL;

		//get all entry objects
		$entfilter = new KalturaMediaEntryFilter();
		if (KalturaContentAnalytics::FILTER_TAGS != null && KalturaContentAnalytics::FILTER_TAGS != '')
			$entfilter->tagsMultiLikeOr = KalturaContentAnalytics::FILTER_TAGS;
		if (KalturaContentAnalytics::PARENT_CATEGORIES != null && KalturaContentAnalytics::PARENT_CATEGORIES != '')
			$entfilter->categoryAncestorIdIn = KalturaContentAnalytics::PARENT_CATEGORIES;
		$entfilter->statusIn = implode(',', KalturaContentAnalytics::ENTRY_STATUS_IN);
		$entfilter->mediaTypeIn = implode(',', KalturaContentAnalytics::ENTRY_TYPE_IN);
		$entries = $this->getFullListOfKalturaObject($entfilter, $this->client->media, 'id', KalturaContentAnalytics::ENTRY_FIELDS, KalturaContentAnalytics::DEBUG_PRINTS, true);
		echo PHP_EOL . 'Total entries to export: ' . count($entries) . PHP_EOL;

		$totalMsDuration = 0;
		foreach ($entries as $entry) {
			$totalMsDuration += $entry['msDuration'];
		}
		echo 'Total minutes of entries exported: ' . number_format($totalMsDuration/1000/60, 2) . PHP_EOL.PHP_EOL;


		//get all categoryEntry objects
		$categories = array();
		$entriesToCategorize = '';
		$catfilter = new KalturaCategoryEntryFilter();
		$N = count($entries);
		reset($entries);
		$eid = key($entries);
		for ($i = 0; $i < $N ; $i++) {
			if ($entriesToCategorize != '') $entriesToCategorize .= ',';
			$entriesToCategorize .= $eid;
			if (($i % KalturaContentAnalytics::CYCLE_SIZES == 0) || ($i == $N-1)) {
				if (KalturaContentAnalytics::DEBUG_PRINTS) {
					echo "\r\033[0K";
					echo 'Categorising: '.($i+1).' entries of '.$N.' total entries...';
				}
				$catfilter->entryIdIn = $entriesToCategorize;
				$catents = $this->getFullListOfKalturaObject($catfilter, $this->client->categoryEntry, 'categoryId', 'entryId*', false);
				foreach ($catents as $catId => $entryIds) {
					$categories[$catId] = true;
					foreach ($entryIds as $entryId) {
						if ( ! isset($entries[$entryId]['categories'])) $entries[$entryId]['categories'] = array();
						$entries[$entryId]['categories'][$catId] = true;
					}
				}
				$entriesToCategorize = '';
			}
			next($entries);
			$eid = key($entries);
		}

		echo PHP_EOL;

		//get all category objects, and map category names to entry objects
		$catfilter = new KalturaCategoryFilter();
		$catsToName = '';
		$N = count($categories);
		reset($categories);
		$categoryId = key($categories);
		for ($i = 0; $i < $N ; $i++) {
			if ($catsToName != '') $catsToName .= ',';
			$catsToName .= $categoryId;
			if (($i % KalturaContentAnalytics::CYCLE_SIZES == 0) || ($i == $N-1)) {
				if (KalturaContentAnalytics::DEBUG_PRINTS) {
					echo "\r\033[0K";
					echo 'Mapping category names: '.($i+1).' categories of '.$N.' total categories...';
				}
				$catfilter->idIn = $catsToName;
				$catnames = $this->getFullListOfKalturaObject($catfilter, $this->client->category, 'id', ['name', 'fullName'], false);
				foreach ($catnames as $catId => $catInfo) {
					$categories[$catId] = $catInfo;
					foreach ($entries as $entryId => $entry) {
						if (isset($entries[$entryId]['categories'][$catId]))
							$entries[$entryId]['categories'][$catId] = $catInfo;
					}
				}
				$catsToName = '';
			}
			next($categories);
			$categoryId = key($categories);
		}

		echo PHP_EOL;

		if (KalturaContentAnalytics::DEBUG_PRINTS) echo 'testing entry categories...'.PHP_EOL;
		// verify categories - we shouldn't be missing any if we're starting from a parent category
		if (KalturaContentAnalytics::PARENT_CATEGORIES != '') {
			foreach ($entries as $eid => $ent) {
				if( ! isset($ent['categories']))
				    echo('Something broke, check entryId: '.$eid.PHP_EOL);
			}
		}
//
		echo PHP_EOL;

		if (KalturaContentAnalytics::DEBUG_PRINTS) echo 'Calculating flavour asset storage size for entries...'.PHP_EOL;
		//get flavours per entry
		$fassetFilter = new KalturaFlavorAssetFilter();
		$pager = new KalturaFilterPager();
		$N = count($entries);
		reset($entries);
		$eid = key($entries);
		$entryIdsInCycle = '';
		$entryFlavourAssets = null;
		for ($i = 0; $i < $N ; $i++) {
			if ($entryIdsInCycle != '') $entryIdsInCycle .= ',';
			$entryIdsInCycle .= $eid;
			if (($i % KalturaContentAnalytics::CYCLE_SIZES == 0) || ($i == $N-1)) {
				if (KalturaContentAnalytics::DEBUG_PRINTS) {
					echo "\r\033[0K";
					echo 'Getting flavour assets: '.($i+1).' entries of '.$N.' total entries...';
				}
				$fassetFilter->entryIdIn = $entryIdsInCycle;
				$fassetFilter->statusEqual = KalturaFlavorAssetStatus::READY;
				$pager->pageSize = 500;
				$pager->pageIndex = 1;
				$entryFlavourAssets = $this->client->flavorAsset->listAction($fassetFilter, $pager);
				while(count($entryFlavourAssets->objects) > 0) {
					foreach ($entryFlavourAssets->objects as $flavour) {
					    if ((!empty($flavour->tags) && strstr('source', $flavour->tags)) || $flavour->flavorParamsId == 0 || $flavour->isOriginal == true){
						$entries[$flavour->entryId]['Source Size']=$flavour->size;
					    }
					    if (!isset($entries[$flavour->entryId]['Total Storage Size'])){
						$entries[$flavour->entryId]['Total Storage Size'] = 0;
					    }
					    $entries[$flavour->entryId]['Total Storage Size']+=$flavour->size;
					}
					++$pager->pageIndex;
					$entryFlavourAssets = $this->client->flavorAsset->listAction($fassetFilter, $pager);
				}
				$entryIdsInCycle = '';
			}
			next($entries);
			$eid = key($entries);
		}

//
		echo PHP_EOL;

		if (KalturaContentAnalytics::DEBUG_PRINTS) echo 'Getting caption assets for the entries...'.PHP_EOL;
		//get captions per entries
		$assetFilter = new KalturaAssetFilter();
		$pager = new KalturaFilterPager();
		$N = count($entries);
		reset($entries);
		$eid = key($entries);
		$entryIdsInCycle = '';
		$entriesCaptions = null;
		for ($i = 0; $i < $N ; $i++) {
			if ($entryIdsInCycle != '') $entryIdsInCycle .= ',';
			$entryIdsInCycle .= $eid;
			if (($i % KalturaContentAnalytics::CYCLE_SIZES == 0) || ($i == $N-1)) {
				if (KalturaContentAnalytics::DEBUG_PRINTS) {
					echo "\r\033[0K";
					echo 'Getting captions: '.($i+1).' entries of '.$N.' total entries...';
				}
				$assetFilter->entryIdIn = $entryIdsInCycle;
				$pager->pageSize = 500;
				$pager->pageIndex = 1;
				$entriesCaptions = $this->client->captionAsset->listAction($assetFilter, $pager);
				while(count($entriesCaptions->objects) > 0) {
					foreach ($entriesCaptions->objects as $capAsset) {
						if ( ! isset($entries[$capAsset->entryId]['captions'])) 
							$entries[$capAsset->entryId]['captions'] = array();
						if (KalturaContentAnalytics::GET_CAPTION_URLS == true) {
							$entries[$capAsset->entryId]['captions-url-'.$capAsset->language] = KalturaContentAnalytics::SERVICE_URL . '/api_v3/service/caption_captionasset/action/serve/captionAssetId/' . $capAsset->id . '/ks/' . $this->ks;
						}
						$entries[$capAsset->entryId]['captions'][] = $capAsset->language;
						$this->captionLanguages[$capAsset->language] = true;
					}
					++$pager->pageIndex;
					$entriesCaptions = $this->client->captionAsset->listAction($assetFilter, $pager);
				}
				$entryIdsInCycle = '';
			}
			next($entries);
			$eid = key($entries);
		}

		echo PHP_EOL;

		if (KalturaContentAnalytics::DEBUG_PRINTS) 
			echo PHP_EOL.'Getting metadata for the entries...'.PHP_EOL;
		//get metadata per entries
		$metadatafilter = new KalturaMetadataFilter();
		$metadatafilter->metadataProfileIdEqual = KalturaContentAnalytics::METADATA_PROFILE_ID;
		$metadatafilter->metadataObjectTypeEqual = KalturaMetadataObjectType::ENTRY;
		$pager = new KalturaFilterPager();
		$metadataPlugin = KalturaMetadataClientPlugin::get($this->client);
		$N = count($entries);
		reset($entries);
		$eid = key($entries);
		$entryIdsInCycle = '';
		$entriesMetadata = null;
		$metadataXml = null;
		for ($i = 0; $i < $N ; $i++) {
			if ($entryIdsInCycle != '') $entryIdsInCycle .= ',';
			$entryIdsInCycle .= $eid;
			if (($i % KalturaContentAnalytics::CYCLE_SIZES == 0) || ($i == $N-1)) {
				if (KalturaContentAnalytics::DEBUG_PRINTS) {
					echo "\r\033[0K";
					echo 'Getting metadata: '.($i+1).' entries of '.$N.' total entries...';
				}
				$metadatafilter->objectIdIn = $entryIdsInCycle;
				$pager->pageSize = 500;
				$pager->pageIndex = 1;
				$entriesMetadata = $metadataPlugin->metadata->listAction($metadatafilter, $pager);
				while(count($entriesMetadata->objects) > 0) {
					foreach ($entriesMetadata->objects as $metadataInstance) {
					    if ( ! isset($entries[$metadataInstance->objectId]['metadata'])) {
						$entries[$metadataInstance->objectId]['metadata'] = array();
					    }
					    $metadataXml = simplexml_load_string($metadataInstance->xml);
					    foreach ($metadataXml->children() as $metadataField) {
						// handle multi choice fields
						if (isset($entries[$metadataInstance->objectId]['metadata'][$metadataField->getName()])){
						    $entries[$metadataInstance->objectId]['metadata'][$metadataField->getName()] .= ' + '.(string)$metadataField ;
						}else{
						    $entries[$metadataInstance->objectId]['metadata'][$metadataField->getName()] = (string)$metadataField;
						}
					    }
					}
					++$pager->pageIndex;
					$entriesMetadata = $metadataPlugin->metadata->listAction($metadatafilter, $pager);
				}
				$entryIdsInCycle = '';
			}
			next($entries);
			$eid = key($entries);
		}

		echo PHP_EOL;

		//create the excel file
		$header = array();
		$header[] = "Entry ID";
		foreach (KalturaContentAnalytics::ENTRY_FIELDS as $entryField) {
		    $words = preg_split('/(?=[A-Z])/',$entryField);
		    $header[] = ucfirst(implode(' ', $words));
		}
		$header[] = "Category IDs";
		$header[] = "Category Names";
		$header[] = "Caption Languages";
		if (KalturaContentAnalytics::GET_CAPTION_URLS == true) {
			foreach ($this->captionLanguages as $language => $exists) {
				$header[] = 'caption-url-'.$language;
			}
		}
		$metadataTemplate = $this->getMetadataTemplate (KalturaContentAnalytics::METADATA_PROFILE_ID, $metadataPlugin);
		foreach ($metadataTemplate->children() as $metadataField) {
			$header[] = "Custom Metadata ".$metadataField->getName();
		}
		$header[] = "Source Size (KB)";
		$header[] = "Total Storage Size (KB)";
		$data = array(1 => $header);

		foreach ($entries as $entry_id => $entry) {
			$row = array();
			$row[] = $entry_id;
			foreach (KalturaContentAnalytics::ENTRY_FIELDS as $entryField) {
			    if ($entryField == 'lastPlayedAt'){
				// special handling is required here since, unlike 'createdAt' and 'updatedAt', this
				// value can be empty if no plays occurred.
				if (empty($entry['views']) || !isset($entry['views'])){
				    $entry['lastPlayedAt']='N/A';
				    $row[] = $entry[$entryField];
				}else{
				    $row[] = gmdate('Y-M-d, h:ia',$entry['lastPlayedAt']);
				}
			    }elseif ($entryField == 'status'){
				$refl = new ReflectionClass('KalturaEntryStatus');
				$statuses = $refl->getConstants();
				$row[] = array_search($entry[$entryField], $statuses);
			    }elseif (in_array($entryField, array('createdAt','updatedAt'))){
				$row[] = gmdate('Y-M-d, h:ia',$entry[$entryField]);
			    }else{
				$row[] = $entry[$entryField];
			    }
			}
			$catIds = '';
			$catNames = '';
			if (isset($entry['categories'])) {
				foreach ($entry['categories'] as $catId => $catName) {
					if ($catIds != '') $catIds .= ',';
					$catIds .= $catId;
					if ($catNames != '') $catNames .= ',';
					$catNames .= $catName['fullName'];
				}
			}
			$row[] = $catIds;
			$row[] = $catNames;

			$capLangs = '';
			if (isset($entry['captions'])) {
				foreach ($entry['captions'] as $captionLanguage) {
					if ($capLangs != '') $capLangs .= ',';
					$capLangs .= $captionLanguage;
				}
			}
			$row[] = $capLangs;
			
			if (KalturaContentAnalytics::GET_CAPTION_URLS == true) {
				foreach ($this->captionLanguages as $language => $exists) {
					$captionUrl = '';
					if (isset($entry['captions-url-'.$language])) {
						$captionUrl = $entry['captions-url-'.$language];
					}
					$row[] = $captionUrl;
				}
			}
			
			if (isset($entry['metadata'])) {
				foreach ($metadataTemplate->children() as $mdfield) {
					if (isset($entry['metadata'][$mdfield->getName()])) {
						$row[] = $entry['metadata'][$mdfield->getName()];
					} else {
						$row[] = '';
					}
				}
			}
			
			if (isset($entry['Source Size'])) {
			    $row[] = $entry['Source Size'];
			}

			if (isset($entry['Total Storage Size'])) {
			    $row[] = $entry['Total Storage Size'];
			}

			if (KalturaContentAnalytics::ONLY_CAPTIONED_ENTRIES == false || (KalturaContentAnalytics::ONLY_CAPTIONED_ENTRIES == true && $capLangs != ''))
				array_push($data,$row);
		}

		$xls = new Excel_XML('UTF-8', false, 'Kaltura Entries');
		$xls->addArray($data);
		$xls->generateSavedXML($this->exportFileName);

		echo 'Successfully exported data!'.PHP_EOL;
		echo 'File name: '.$this->exportFileName.'.xls'.PHP_EOL;
	}

	public function getFullListOfKalturaObject ($filter, $listService, $idField = 'id', $valueFields = NULL, $printProgress = FALSE, $stopOnCreatedAtDate = false) {
		$serviceName = get_class($listService);
		$filter->orderBy = '-createdAt';
		$filter->createdAtLessThanOrEqual = NULL;
		$pager = new KalturaFilterPager();
		$pager->pageSize = 500;
		$pager->pageIndex = 1;
		$lastCreatedAt = 0;
		$lastObjectIds = '';
		$reachedLastObject = false;
		$allObjects = array();
		$count = 0;
		$totalCount = 0;

		$countAvailable = method_exists($listService, 'count');
		if ($countAvailable) {
			if ( $stopOnCreatedAtDate && $this->stopDateForCreatedAtFilter != null && $this->stopDateForCreatedAtFilter > -1) {
				$filter->createdAtGreaterThanOrEqual = $this->stopDateForCreatedAtFilter;
			}
			$totalCount = $listService->count($filter)+1; //due to date filter grater vs. less-than there will be a 1 diff
			$filter->createdAtGreaterThanOrEqual = KalturaClientBase::getKalturaNullValue();
		}
		
		// if this filter doesn't have idNotIn - we need to find the highest totalCount
		// this is a workaround hack due to a bug in how categoryEntry list action calculates totalCount
		if ( ! property_exists($filter, 'idNotIn')) {
			$temppager = new KalturaFilterPager();
			$temppager->pageSize = 500;
			$temppager->pageIndex = 1;
			$result = $listService->listAction($filter, $temppager);
			while(count($result->objects) > 0) {
				$result = $listService->listAction($filter, $temppager);
				$totalCount = max($totalCount, $result->totalCount);
				++$temppager->pageIndex;
			}
		}
		if ($printProgress && $totalCount > 0) {
			echo $serviceName.' Progress (total: ' . $totalCount .'):      ';
			echo PHP_EOL;
		}
		while ( ! $reachedLastObject) {
			if($lastCreatedAt != 0)
				$filter->createdAtLessThanOrEqual = $lastCreatedAt;

			if($lastObjectIds != '' && property_exists($filter, 'idNotIn'))
				$filter->idNotIn = $lastObjectIds;
			
			try {

				$filteredListResult = $listService->listAction($filter, $pager);

			} catch (Exception $err) {
				echo 'Message: ' .$err->getMessage().PHP_EOL;
				echo '===========ERROR=========='.PHP_EOL;
				echo 'Last Kaltura client headers:'.PHP_EOL;
				print_r($this->client->getResponseHeaders());
			}
			
			if ($totalCount == 0) $totalCount = $filteredListResult->totalCount;

			$resultsCount = count($filteredListResult->objects);

			if ( $resultsCount == 0 || $totalCount <= $count ) {
				$reachedLastObject = true;
				break;
			}
			
			foreach ($filteredListResult->objects as $obj) {
				if ($count < $totalCount) {
					if ($valueFields == NULL) {
						$allObjects[$obj->{$idField}] = $obj;
					} elseif (is_string($valueFields)) {
						if (substr($valueFields, -1) == '*') {
							$valfield = substr($valueFields, 0, -1);
							if (! isset($allObjects[$obj->{$idField}]))
								$allObjects[$obj->{$idField}] = array();
							$allObjects[$obj->{$idField}][] = $obj->{$valfield};
						} else {
							$allObjects[$obj->{$idField}] = $obj->{$valueFields};
						}
					} elseif (is_array($valueFields)) {
						if (! isset($allObjects[$obj->{$idField}]))
							$allObjects[$obj->{$idField}] = array();
						foreach ($valueFields as $field) {
							$allObjects[$obj->{$idField}][$field] = $obj->{$field};
						}
					}
					if($lastCreatedAt > $obj->createdAt) 
						$lastObjectIds = '';
					$lastCreatedAt = $obj->createdAt;

					if ( $stopOnCreatedAtDate && $this->stopDateForCreatedAtFilter != null && $this->stopDateForCreatedAtFilter > -1 && 
						$lastCreatedAt <= $this->stopDateForCreatedAtFilter ) {
						$reachedLastObject = true;
						break;
					}

		     		if($lastObjectIds != '') $lastObjectIds .= ',';
					$lastObjectIds .= $obj->{$idField};
				} else {
					$reachedLastObject = true;
					break;
				}
			}

			$count += $resultsCount;

			if ($printProgress && $totalCount > 0) {
				$perc = min(100,$count / $totalCount * 100);
				if ($perc < 100) $perc = number_format($perc, 2);
				echo "\r\033[0K";
				echo $perc.'%';
				flush();
			}
		}
		if ($printProgress && $totalCount > 0) {
			echo PHP_EOL;
		}
		return $allObjects;
	}

	public function getMetadataTemplate ($metadataProfileId, $metadataPlugin) {

		// if no valid profile id was provided, return an empty metadata
		if ($metadataProfileId <= 0) {
			$metadataTemplate = '<metadata>'; //Kaltura metadata XML is always wrapped in <metadata>
			$metadataTemplate .= '</metadata>';
			$metadataXmlTemplate = simplexml_load_string($metadataTemplate);
			return $metadataXmlTemplate;
		}

		$schemaUrl = $metadataPlugin->metadataProfile->serve($metadataProfileId); //returns a URL
		//or can also use: $metadataPlugin->metadataProfile->get($metadataProfileId)->xsd
		$schemaXSDFile = file_get_contents($schemaUrl); //download the XSD file from Kaltura

		//Build a <metadata> template:
		$schema = new DOMDocument();
		$schema->loadXML(str_replace('&', '&amp;', $schemaXSDFile)); //load and parse the XSD as an XML
		$fieldsList = $schema->getElementsByTagName('element'); //get all elements of the XSD
		$metadataTemplate = '<metadata>'; //Kaltura metadata XML is always wrapped in <metadata>
		foreach ($fieldsList as $element) {
		    if ($element->hasAttribute('name') === false) {
			continue; //valid fields will always have name
		    }
		    $key = $element->getAttribute('name'); //systemName is the element's name, not key nor id
		    if ($key != 'metadata') { //exclude the parent node ‘metadata' as we're manually creating it
			if ($element->getAttribute('type') != 'textType') {
			    $options = $element->getElementsByTagName('enumeration');
				    if ($options != null && ($options->length > 0)) {
					    $defaultOption = $options->item(0)->nodeValue;
					    $metadataTemplate .= '<' . $key . '>' . $defaultOption . '</' . $key . '>';
				    } else {
					    $metadataTemplate .= '<' . $key . '>' . '</' . $key . '>';
				    }
			} else {
			    $metadataTemplate .= '<' . $key . '>' . '</' . $key . '>';
			}
		    }
		}
		$metadataTemplate .= '</metadata>';
		$metadataXmlTemplate = simplexml_load_string($metadataTemplate);
		return $metadataXmlTemplate;
	}

	/**
	 * Converts a string to a valid UNIX filename.
	 * @param $string The filename to be converted
	 * @return $string The filename converted
	 */
	private function convert_to_filename ($string) {

	  // Replace spaces with underscores and makes the string lowercase
	  $string = str_replace (" ", "_", $string);
	  $string = str_replace ("..", ".", $string);
	  $string = strtolower ($string);

	  // Match any character that is not in our whitelist
	  preg_match_all ("/[^0-9^a-z^_^.]/", $string, $matches);

	  // Loop through the matches
	  foreach ($matches[0] as $value) {
	    $string = str_replace($value, "", $string);
	  }
	  return $string;
	}
}
$instance = new KalturaContentAnalytics();
$instance->run();
